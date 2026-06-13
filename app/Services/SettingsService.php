<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Page;
use App\Models\Settings;
use Illuminate\Database\Eloquent\Collection;

final readonly class SettingsService
{
    public function __construct(private ?Settings $settings) {}

    public static function current(): self
    {
        return new self(Settings::cached());
    }

    public function faviconUrl(string $crop = 'default'): ?string
    {
        $settings = $this->settings;
        if (! $settings instanceof Settings) {
            return null;
        }

        $favicon = $settings->media->first(fn (Media $media): bool => $media->type === MediaType::IMAGE
            && $media->pivot->role === 'favicon'
        );

        return $favicon ? $settings->image('favicon', $crop, ['fm' => 'png'], false, $favicon) : null;
    }

    /**
     * @return array<string, string>
     */
    public function themeColors(): array
    {
        $theme = (string) data_get($this->settings?->metadata, 'theme', '');

        if ($theme === '') {
            return [];
        }

        $colors = $theme === 'custom'
            ? data_get($this->settings?->metadata, 'colors', [])
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

        $radiusKey = (string) data_get($this->settings?->metadata, 'radius', '') ?: config()->string('theme.default_radius');
        $radius = config()->string("theme.radii.$radiusKey", '');
        if ($radius !== '') {
            foreach (['sm', 'md', 'lg', 'xl'] as $size) {
                $root[] = "--radius-$size:$radius";
            }
        }

        $headingFontKey = (string) data_get($this->settings?->metadata, 'heading_font', '') ?: config()->string('theme.default_font');
        $bodyFontKey = (string) data_get($this->settings?->metadata, 'body_font', '') ?: config()->string('theme.default_font');
        $headingStack = config()->string("theme.fonts.$headingFontKey.stack", '');
        $bodyStack = config()->string("theme.fonts.$bodyFontKey.stack", '');
        if ($headingStack !== '') {
            $root[] = "--wire-heading-font:$headingStack";
        }
        if ($bodyStack !== '') {
            $root[] = "--wire-body-font:$bodyStack";
            $root[] = "--font-sans:$bodyStack";
        }

        $headingSizeKey = (string) data_get($this->settings?->metadata, 'heading_size', '') ?: config()->string('theme.default_heading_size');
        $bodySizeKey = (string) data_get($this->settings?->metadata, 'body_size', '') ?: config()->string('theme.default_body_size');
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
            $font = (string) data_get($this->settings?->metadata, $slot, '');
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

        $menus = data_get($this->settings?->metadata, $location.'_menu');
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
        $social = data_get($this->settings?->metadata, 'social');
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
