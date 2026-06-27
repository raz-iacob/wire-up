<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
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

    public function description(?Page $page, string $stored): string
    {
        $stored = mb_trim($stored);

        if ($stored !== '') {
            return $stored;
        }

        if ($page instanceof Page) {
            $excerpt = $page->textExcerpt(160);

            if ($excerpt !== '') {
                return $excerpt;
            }
        }

        return SettingsService::current()->description();
    }

    public function robots(?Page $page): string
    {
        if (SettingsService::current()->noindex() || $page?->isNoindex()) {
            return 'noindex, nofollow';
        }

        return 'index, follow, max-image-preview:large';
    }

    public function ogImageUrl(?Page $page): ?string
    {
        if ($page instanceof Page) {
            $page->loadMissing('media');

            $image = $page->image('og_image', 'desktop', ['w' => 1200, 'h' => 630], false);

            if (is_string($image) && $image !== '') {
                return $image;
            }
        }

        return SettingsService::current()->defaultOgImageUrl();
    }

    /**
     * @return array<string, string>
     */
    public function hreflangAlternates(?Page $page): array
    {
        if (! $this->isMultilocale() || ! $page instanceof Page) {
            return [];
        }

        $alternates = $this->localeUrls($page);

        if ($alternates !== []) {
            $default = $this->localization()->getDefaultLocale();
            $alternates['x-default'] = $alternates[$default] ?? reset($alternates);
        }

        return $alternates;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonLd(?Page $page): array
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

        if ($page instanceof Page) {
            $url = $this->canonicalUrl();
            $description = $this->description($page, $page->description);

            $webpage = [
                '@type' => 'WebPage',
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $page->title,
                'inLanguage' => app()->getLocale(),
                'isPartOf' => ['@id' => $home.'#website'],
                'dateModified' => $page->updated_at->toAtomString(),
            ];

            if ($description !== '') {
                $webpage['description'] = $description;
            }

            $image = $this->ogImageUrl($page);
            if ($image !== null) {
                $webpage['primaryImageOfPage'] = $image;
            }

            $graph[] = $webpage;

            if ($page->id !== $this->homeId()) {
                $graph[] = [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'), 'item' => $home],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $page->title, 'item' => $url],
                    ],
                ];
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

        foreach ($this->publishedPages() as $page) {
            $alternates = $this->localeUrls($page);
            $lastmod = $page->updated_at->toAtomString();

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
        }

        return implode("\n", $lines)."\n";
    }

    public function llmsFullTxt(): string
    {
        $settings = SettingsService::current();
        $lines = $this->llmsHeader($settings);

        if (! $settings->noindex()) {
            foreach ($this->publishedPages(withBlocks: true) as $page) {
                $url = $this->primaryUrl($page);

                if ($url === '') {
                    continue;
                }

                $title = $page->title !== '' ? $page->title : ($settings->title() ?: config()->string('app.name'));

                $lines[] = '# '.$title;
                $lines[] = $url;
                $lines[] = '';
                $lines[] = $page->plainText();
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
    private function localeUrls(Page $page): array
    {
        $page->loadMissing('slugs');

        $isHome = $page->id === $this->homeId();
        $urls = [];

        foreach ($this->localesForPage($page) as $locale) {
            $url = $isHome
                ? $this->localization()->getLocalizedURL(route('home'), $locale)
                : ($page->getSlug($locale) !== '' ? $this->localization()->getLocalizedURL($page->getUrl($locale), $locale) : '');

            if ($url !== '') {
                $urls[$locale] = $url;
            }
        }

        return $urls;
    }

    private function primaryUrl(Page $page): string
    {
        if ($page->id === $this->homeId()) {
            return route('home');
        }

        $page->loadMissing('slugs');

        return $page->getSlug() !== '' ? $page->getUrl() : '';
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
            ->reject(fn (Page $page): bool => $page->isNoindex())
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function localesForPage(Page $page): array
    {
        if (! $this->isMultilocale()) {
            return [$this->localization()->getDefaultLocale()];
        }

        return array_values(array_intersect($this->activeCodes(), $page->published_locales));
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
