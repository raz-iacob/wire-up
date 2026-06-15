<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

final class SettingsService
{
    public static function current(): self
    {
        return new self;
    }

    public function homePage(): ?Page
    {
        $configured = config('site.home_page_id');

        if (is_numeric($configured)) {
            $page = Page::query()
                ->published()
                ->with(['translations', 'slugs'])
                ->whereKey((int) $configured)
                ->first();

            if ($page instanceof Page) {
                return $page;
            }
        }

        return Page::query()
            ->published()
            ->with(['translations', 'slugs'])
            ->whereHas('slugs', function (Builder $query): void {
                $query->where('slug', 'home');
            })
            ->orderBy('id')
            ->first();
    }

    public function homePageId(): ?int
    {
        return $this->homePage()?->id;
    }

    public function title(): string
    {
        return $this->localeValue(config('site.title'));
    }

    public function description(): string
    {
        return $this->localeValue(config('site.description'));
    }

    public function logoUrl(string $role, string $crop = 'default'): ?string
    {
        $item = config('site.'.$role);

        return $this->imageUrl(is_array($item) ? $item : null, $crop, [], requireCrop: true);
    }

    public function faviconUrl(string $crop = 'default'): ?string
    {
        $item = config('site.favicon');

        return $this->imageUrl(is_array($item) ? $item : null, $crop, ['fm' => 'png'], requireCrop: false);
    }

    /**
     * @return array<string, string>
     */
    public function themeColors(): array
    {
        $theme = (string) config('site.theme', '');

        if ($theme === '') {
            return [];
        }

        $colors = $theme === 'custom'
            ? config('site.colors', [])
            : config()->array("theme.presets.$theme.colors", []);

        if (! is_array($colors)) {
            return [];
        }

        $palette = [];
        foreach ($colors as $slot => $hex) {
            if (is_string($slot) && is_string($hex)) {
                $palette[$slot] = $hex;
            }
        }

        return $palette;
    }

    public function themeCss(): string
    {
        $source = $this->themeColors() ?: config()->array('theme.presets.default.colors');
        $palette = [];
        foreach ($source as $slot => $hex) {
            if (is_string($slot) && is_string($hex)) {
                $palette[$slot] = $hex;
            }
        }

        $root = [];
        foreach ($palette as $slot => $hex) {
            $name = match ($slot) {
                'background' => 'body-bg',
                'text' => 'body-text',
                default => str_replace('_', '-', $slot),
            };
            $root[] = "--wire-$name:$hex";
        }

        $dark = [];
        if (isset($palette['primary_bg'], $palette['primary_text'])) {
            $accent = [
                "--color-accent:{$palette['primary_bg']}",
                "--color-accent-content:{$palette['primary_bg']}",
                "--color-accent-foreground:{$palette['primary_text']}",
            ];
            $root = [...$root, ...$accent];
            $dark = $accent;
        }

        $radiusKey = (string) config('site.radius', '') ?: config()->string('theme.default_radius');
        $radius = config()->string("theme.radii.$radiusKey", '');
        if ($radius !== '') {
            foreach (['sm', 'md', 'lg', 'xl'] as $size) {
                $root[] = "--radius-$size:$radius";
            }
        }

        $headingFontKey = (string) config('site.heading_font', '') ?: config()->string('theme.default_font');
        $bodyFontKey = (string) config('site.body_font', '') ?: config()->string('theme.default_font');
        $headingStack = config()->string("theme.fonts.$headingFontKey.stack", '');
        $bodyStack = config()->string("theme.fonts.$bodyFontKey.stack", '');
        if ($headingStack !== '') {
            $root[] = "--wire-heading-font:$headingStack";
        }
        if ($bodyStack !== '') {
            $root[] = "--wire-body-font:$bodyStack";
            $root[] = "--font-sans:$bodyStack";
        }

        $headingSizeKey = (string) config('site.heading_size', '') ?: config()->string('theme.default_heading_size');
        $bodySizeKey = (string) config('site.body_size', '') ?: config()->string('theme.default_body_size');
        $headingSize = config()->string("theme.heading_sizes.$headingSizeKey", '');
        $bodySize = config()->string("theme.body_sizes.$bodySizeKey", '');
        if ($headingSize !== '') {
            $root[] = "--wire-heading-size:$headingSize";
        }
        if ($bodySize !== '') {
            $root[] = "--wire-body-size:$bodySize";
        }

        return ':root{'.implode(';', $root).'}'.($dark === [] ? '' : '.dark{'.implode(';', $dark).'}');
    }

    public function googleFontsUrl(): ?string
    {
        $families = [];
        foreach (['heading_font', 'body_font'] as $slot) {
            $font = (string) config('site.'.$slot, '');
            $family = config()->string("theme.fonts.$font.google", '');
            if ($family !== '') {
                $families[$family] = $family;
            }
        }

        if ($families === []) {
            return null;
        }

        $params = implode('&', array_map(
            fn (string $family): string => 'family='.str_replace(' ', '+', $family).':wght@400;500;600;700',
            $families,
        ));

        return "https://fonts.googleapis.com/css2?$params&display=swap";
    }

    /**
     * @return array<int, array{label: string, url: string, target: string, appearance: string}>
     */
    public function menu(string $location): array
    {
        if (! in_array($location, ['header', 'footer'], true)) {
            return [];
        }

        $menus = config('site.'.$location.'_menu');
        if (! is_array($menus)) {
            return [];
        }

        $items = $this->localeMenuItems($menus);
        $pages = $this->menuPages($items);

        $resolved = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $entry = $this->resolveMenuItem($item, $pages);
            if ($entry !== null) {
                $resolved[] = $entry;
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $social = config('site.social');
        if (! is_array($social)) {
            return [];
        }

        $links = [];
        foreach (array_keys(config()->array('social.platforms')) as $platform) {
            $key = (string) $platform;
            $url = $social[$key] ?? null;
            if (is_string($url) && $url !== '') {
                $links[$key] = $url;
            }
        }

        return $links;
    }

    public function socialIconVariant(): string
    {
        $variant = (string) config('site.social_icon_variant', '');

        return array_key_exists($variant, config()->array('social.icon_variants'))
            ? $variant
            : config()->string('social.default_icon_variant', 'solid');
    }

    private function localeValue(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        $candidates = [app()->getLocale(), config()->string('app.fallback_locale', 'en')];
        foreach ($candidates as $locale) {
            if (is_string($value[$locale] ?? null) && $value[$locale] !== '') {
                return $value[$locale];
            }
        }

        foreach ($value as $text) {
            if (is_string($text) && $text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>|null  $item
     * @param  array<string, mixed>  $params
     */
    private function imageUrl(?array $item, string $crop, array $params, bool $requireCrop): ?string
    {
        $source = $item['source'] ?? null;
        if (! is_string($source) || $source === '') {
            return null;
        }

        $crops = is_array($item['crop'] ?? null) ? $item['crop'] : [];
        $variant = is_array($crops[$crop] ?? null) ? $crops[$crop] : null;

        if ($requireCrop && $variant === null && ! str_ends_with(mb_strtolower($source), '.svg')) {
            return null;
        }

        return route('image.show', [
            'options' => $this->cropString([...($variant ?? []), ...$params]),
            'path' => $source,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function cropString(array $params): string
    {
        $default = [
            'crop_w' => 1200,
            'crop_h' => 800,
            'crop_x' => 0,
            'crop_y' => 0,
            'w' => 1200,
            'h' => 800,
            'q' => 80,
            'fm' => 'jpg',
        ];

        $values = [...$default, ...Arr::only($params, array_keys($default))];

        $crop = sprintf(
            '%d-%d-%d-%d',
            $values['crop_w'],
            $values['crop_h'],
            $values['crop_x'],
            $values['crop_y']
        );

        return implode(',', [
            "w={$values['w']}",
            "h={$values['h']}",
            "crop={$crop}",
            "q={$values['q']}",
            "fm={$values['fm']}",
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $menus
     * @return array<int, mixed>
     */
    private function localeMenuItems(array $menus): array
    {
        $locale = app()->getLocale();
        $items = $menus[$locale] ?? null;

        if (is_array($items) && $items !== []) {
            return array_values($items);
        }

        $default = config()->string('app.fallback_locale', 'en');
        if ($locale !== $default && is_array($menus[$default] ?? null)) {
            return array_values($menus[$default]);
        }

        return [];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return Collection<int, Page>
     */
    private function menuPages(array $items): Collection
    {
        $ids = [];
        foreach ($items as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'page' && isset($item['page_id'])) {
                $ids[] = (int) $item['page_id'];
            }
        }

        if ($ids === []) {
            return new Collection;
        }

        return Page::query()
            ->published()
            ->whereIn('id', $ids)
            ->with('slugs')
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<int, Page>  $pages
     * @return array{label: string, url: string, target: string, appearance: string}|null
     */
    private function resolveMenuItem(array $item, Collection $pages): ?array
    {
        $label = is_string($item['label'] ?? null) ? $item['label'] : '';
        if ($label === '') {
            return null;
        }

        $type = $item['type'] ?? null;
        $url = null;

        if ($type === 'link') {
            $url = is_string($item['url'] ?? null) && $item['url'] !== '' ? $item['url'] : null;
        } elseif ($type === 'page') {
            $pageId = isset($item['page_id']) ? (int) $item['page_id'] : null;
            $page = $pageId !== null ? $pages->get($pageId) : null;
            if ($page instanceof Page && $page->slug !== '') {
                $url = route('page', $page->slug);
            }
        }

        if ($url === null) {
            return null;
        }

        return [
            'label' => $label,
            'url' => $url,
            'target' => ($item['target'] ?? null) === '_blank' ? '_blank' : '_self',
            'appearance' => ($item['appearance'] ?? null) === 'button' ? 'button' : 'link',
        ];
    }
}
