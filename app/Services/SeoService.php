<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Record;
use Illuminate\Database\Eloquent\Collection;

final class SeoService
{
    private ?int $homeId = null;

    private bool $homeResolved = false;

    public static function current(): self
    {
        return new self;
    }

    public function canonicalUrl(): string
    {
        return url()->current();
    }

    public function description(Page|Record|null $content, string $stored): string
    {
        $stored = mb_trim($stored);

        if ($stored !== '') {
            return $stored;
        }

        if ($content !== null) {
            $excerpt = $content->textExcerpt(160);

            if ($excerpt !== '') {
                return $excerpt;
            }
        }

        return SettingsService::current()->description();
    }

    public function robots(Page|Record|null $content): string
    {
        if (SettingsService::current()->noindex() || $content?->isNoindex() || $content?->isMembersOnly()) {
            return 'noindex, nofollow';
        }

        return 'index, follow, max-image-preview:large';
    }

    public function ogImageUrl(Page|Record|null $content): ?string
    {
        if ($content !== null) {
            $content->loadMissing('media');

            $image = $content->image('og_image', 'desktop', ['w' => 1200, 'h' => 630], false);

            if (is_string($image) && $image !== '') {
                return $image;
            }
        }

        return SettingsService::current()->defaultOgImageUrl();
    }

    /**
     * @return array<string, string>
     */
    public function hreflangAlternates(Page|Record|null $content): array
    {
        if (! $this->isMultilocale() || $content === null) {
            return [];
        }

        $alternates = $this->localeUrls($content);

        if ($alternates !== []) {
            $default = $this->localization()->getDefaultLocale();
            $alternates['x-default'] = $alternates[$default] ?? reset($alternates);
        }

        return $alternates;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonLd(Page|Record|null $content): array
    {
        $settings = SettingsService::current();
        $home = route('home');
        $name = $settings->title() ?: config()->string('app.name');

        $organization = [
            '@type' => 'Organization',
            '@id' => $home.'#organization',
            'name' => $name,
            'url' => $home,
        ];

        $logo = $settings->logoUrl('logo_header');
        if ($logo !== null) {
            $organization['logo'] = $logo;
        }

        $social = array_values($settings->socialLinks());
        if ($social !== []) {
            $organization['sameAs'] = $social;
        }

        $graph = [
            $organization,
            [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'name' => $name,
                'url' => $home,
                'publisher' => ['@id' => $home.'#organization'],
            ],
        ];

        if ($content !== null) {
            $url = $this->canonicalUrl();
            $title = $content->title;
            $description = $this->description($content, $content->description);
            $image = $this->ogImageUrl($content);

            $webpage = [
                '@type' => 'WebPage',
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $title,
                'inLanguage' => app()->getLocale(),
                'isPartOf' => ['@id' => $home.'#website'],
                'dateModified' => $content->updated_at->toAtomString(),
            ];

            if ($description !== '') {
                $webpage['description'] = $description;
            }

            if ($image !== null) {
                $webpage['primaryImageOfPage'] = $image;
            }

            $graph[] = $webpage;

            if (! $this->isHome($content)) {
                $graph[] = [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'), 'item' => $home],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $url],
                    ],
                ];
            }

            if ($content instanceof Record) {
                $recordSchema = new RecordSchema($home.'#organization', $image);
                $graph = [...$graph, ...$recordSchema->nodes($content, $url, $description)];
            }

            $blockSchema = new BlockSchema(
                $title !== '' ? $title : $name,
                $home.'#organization',
                $image,
            );

            $content->loadMissing('blocks');

            foreach ($content->blocks as $block) {
                $graph = [...$graph, ...$blockSchema->nodes($block, app()->getLocale())];
            }
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    public function sitemapXml(): string
    {
        if (SettingsService::current()->noindex()) {
            return $this->wrapUrlset('');
        }

        $this->homeId();
        $multilocale = $this->isMultilocale();
        $default = $this->localization()->getDefaultLocale();

        $body = '';

        foreach ([...$this->publishedPages(), ...$this->publishedRecords()] as $content) {
            $alternates = $this->localeUrls($content);
            $lastmod = $content->updated_at->toAtomString();

            foreach ($alternates as $url) {
                $body .= '<url><loc>'.$this->xml($url).'</loc><lastmod>'.$lastmod.'</lastmod>';

                if ($multilocale) {
                    foreach ($alternates as $locale => $href) {
                        $body .= '<xhtml:link rel="alternate" hreflang="'.$this->xml($locale).'" href="'.$this->xml($href).'"/>';
                    }

                    $body .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.$this->xml($alternates[$default] ?? reset($alternates)).'"/>';
                }

                $body .= '</url>';
            }
        }

        return $this->wrapUrlset($body);
    }

    public function llmsTxt(): string
    {
        $settings = SettingsService::current();
        $lines = $this->llmsHeader($settings);

        if (! $settings->noindex()) {
            $lines[] = '## Pages';
            $lines[] = '';

            foreach ($this->publishedPages(withBlocks: true) as $page) {
                $url = $this->primaryUrl($page);

                if ($url === '') {
                    continue;
                }

                $title = $page->title !== '' ? $page->title : ($settings->title() ?: config()->string('app.name'));
                $description = $this->description($page, $page->description);

                $lines[] = '- ['.$title.']('.$url.')'.($description !== '' ? ': '.$description : '');
            }

            foreach ($this->publishedRecords(withBlocks: true)->groupBy(fn (Record $record): string => $record->recordType->name) as $group => $records) {
                $lines[] = '';
                $lines[] = '## '.$group;
                $lines[] = '';

                foreach ($records as $record) {
                    $url = $this->primaryUrl($record);

                    if ($url === '') {
                        continue;
                    }

                    $description = $this->description($record, $record->description);

                    $lines[] = '- ['.$record->title.']('.$url.')'.($description !== '' ? ': '.$description : '');
                }
            }
        }

        return implode("\n", $lines)."\n";
    }

    public function llmsFullTxt(): string
    {
        $settings = SettingsService::current();
        $lines = $this->llmsHeader($settings);

        if (! $settings->noindex()) {
            $fallbackTitle = $settings->title() ?: config()->string('app.name');

            foreach ([...$this->publishedPages(withBlocks: true), ...$this->publishedRecords(withBlocks: true)] as $content) {
                $url = $this->primaryUrl($content);

                if ($url === '') {
                    continue;
                }

                $lines[] = '# '.($content->title !== '' ? $content->title : $fallbackTitle);
                $lines[] = $url;
                $lines[] = '';
                $lines[] = $content->plainText();
                $lines[] = '';
                $lines[] = '---';
                $lines[] = '';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<int, string>
     */
    private function llmsHeader(SettingsService $settings): array
    {
        $lines = ['# '.($settings->title() ?: config()->string('app.name')), ''];

        $tagline = $settings->description();
        if ($tagline !== '') {
            $lines[] = '> '.$tagline;
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function localeUrls(Page|Record $content): array
    {
        $content->loadMissing('slugs');

        $isHome = $this->isHome($content);
        $urls = [];

        foreach ($this->localesForContent($content) as $locale) {
            $url = $isHome
                ? $this->localization()->getLocalizedURL(route('home'), $locale)
                : ($content->getSlug($locale) !== '' ? $this->localization()->getLocalizedURL($content->getUrl($locale), $locale) : '');

            if ($url !== '') {
                $urls[$locale] = $url;
            }
        }

        return $urls;
    }

    private function primaryUrl(Page|Record $content): string
    {
        if ($this->isHome($content)) {
            return route('home');
        }

        $content->loadMissing('slugs');

        return $content->getSlug() !== '' ? $content->getUrl() : '';
    }

    private function isHome(Page|Record $content): bool
    {
        return $content instanceof Page && $content->id === $this->homeId();
    }

    /**
     * @return Collection<int, Page>
     */
    private function publishedPages(bool $withBlocks = false): Collection
    {
        $with = ['slugs', 'translations'];
        if ($withBlocks) {
            $with[] = 'blocks';
        }

        return Page::query()
            ->published()
            ->with($with)
            ->get()
            ->reject(fn (Page $page): bool => $page->isNoindex() || $page->isMembersOnly())
            ->values();
    }

    /**
     * @return Collection<int, Record>
     */
    private function publishedRecords(bool $withBlocks = false): Collection
    {
        $with = ['recordType', 'slugs', 'translations'];
        if ($withBlocks) {
            $with[] = 'blocks';
        }

        return Record::query()
            ->published()
            ->with($with)
            ->get()
            ->reject(fn (Record $record): bool => $record->isNoindex() || $record->isMembersOnly())
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function localesForContent(Page|Record $content): array
    {
        if (! $this->isMultilocale()) {
            return [$this->localization()->getDefaultLocale()];
        }

        return array_values(array_intersect($this->activeCodes(), $content->published_locales));
    }

    /**
     * @return array<int, string>
     */
    private function activeCodes(): array
    {
        return $this->localization()->getActiveLocaleCodes()->all();
    }

    private function isMultilocale(): bool
    {
        return count($this->activeCodes()) > 1;
    }

    private function homeId(): ?int
    {
        if (! $this->homeResolved) {
            $this->homeId = SettingsService::current()->homePageId();
            $this->homeResolved = true;
        }

        return $this->homeId;
    }

    private function localization(): LocalizationService
    {
        return resolve('localization');
    }

    private function wrapUrlset(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'
            .$body
            .'</urlset>'."\n";
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
