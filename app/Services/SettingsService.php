<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

final class SettingsService
{
    /**
     * @var array<int, string>
     */
    public const array BUILTIN_MENUS = ['header', 'footer'];

    public static function current(): self
    {
        return new self;
    }

    /**
     * @return array<int, array{key: string, name: string, builtin: bool, display: array{background: bool, position: string, sticky: bool, mobile: string}, items: array<string, array<int, mixed>>}>
     */
    public static function normalizeMenus(mixed $stored): array
    {
        $byKey = [];

        if (is_array($stored)) {
            foreach ($stored as $menu) {
                $key = is_array($menu) && is_string($menu['key'] ?? null) ? $menu['key'] : '';

                if ($key === '') {
                    continue;
                }

                $byKey[$key] = [
                    'key' => $key,
                    'name' => is_string($menu['name'] ?? null) && $menu['name'] !== '' ? $menu['name'] : ucfirst($key),
                    'builtin' => in_array($key, self::BUILTIN_MENUS, true),
                    'display' => self::normalizeMenuDisplay($menu['display'] ?? null),
                    'items' => is_array($menu['items'] ?? null) ? $menu['items'] : [],
                ];
            }
        }

        $ordered = [];

        foreach (self::BUILTIN_MENUS as $builtin) {
            $ordered[] = $byKey[$builtin] ?? [
                'key' => $builtin,
                'name' => ucfirst($builtin),
                'builtin' => true,
                'display' => self::normalizeMenuDisplay(null),
                'items' => [],
            ];

            unset($byKey[$builtin]);
        }

        return [...$ordered, ...array_values($byKey)];
    }

    /**
     * @return array{background: bool, position: string, sticky: bool, mobile: string}
     */
    public static function normalizeMenuDisplay(mixed $display): array
    {
        $display = is_array($display) ? $display : [];

        return [
            'background' => (bool) ($display['background'] ?? true),
            'position' => ($display['position'] ?? null) === 'right' ? 'right' : 'left',
            'sticky' => (bool) ($display['sticky'] ?? false),
            'mobile' => in_array($display['mobile'] ?? null, ['collapse', 'hide', 'toggle'], true) ? $display['mobile'] : 'collapse',
        ];
    }

    /**
     * @return array{display: array{background: bool, position: string, sticky: bool, mobile: string}, items: array<int, array{type: string, label: string, url: string, target: string, appearance: string, icon: ?string, badge: string, badgeColor: string}>}|null
     */
    public function menuForDisplay(string $key): ?array
    {
        $menu = collect($this->allMenus())->firstWhere('key', $key);

        if ($menu === null) {
            return null;
        }

        $items = $this->menu($key);

        if ($items === []) {
            return null;
        }

        return [
            'display' => $menu['display'],
            'items' => $items,
        ];
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

    public function contactEmail(): string
    {
        $email = config('site.contact_email');

        return is_string($email) ? $email : '';
    }

    public function noindex(): bool
    {
        return (bool) config('site.noindex', false);
    }

    public function googleAnalyticsId(): string
    {
        $id = config('site.google_analytics_id');

        return is_string($id) ? $id : '';
    }

    public function logoUrl(string $role, string $crop = 'default', int $maxHeight = 320): ?string
    {
        $item = config('site.'.$role);

        if (! is_array($item)) {
            return null;
        }

        $source = $item['source'] ?? null;

        if (! is_string($source) || $source === '') {
            return null;
        }

        $crops = is_array($item['crop'] ?? null) ? $item['crop'] : [];
        $variant = is_array($crops[$crop] ?? null) ? $crops[$crop] : null;

        $options = ["h={$maxHeight}", 'q=80', 'fm=png'];

        if ($variant !== null && ($variant['crop_w'] ?? 0) > 0 && ($variant['crop_h'] ?? 0) > 0) {
            $options[] = sprintf(
                'crop=%d-%d-%d-%d',
                $variant['crop_w'],
                $variant['crop_h'],
                $variant['crop_x'] ?? 0,
                $variant['crop_y'] ?? 0,
            );
        }

        return route('image.show', [
            'options' => implode(',', $options),
            'path' => $source,
        ]);
    }

    public function faviconUrl(string $crop = 'default'): ?string
    {
        $item = config('site.favicon');

        return $this->imageUrl(is_array($item) ? $item : null, $crop, ['fm' => 'png']);
    }

    public function defaultOgImageUrl(string $crop = 'default'): ?string
    {
        $item = config('site.default_og_image');

        return $this->imageUrl(is_array($item) ? $item : null, $crop, ['w' => 1200, 'h' => 630, 'fm' => 'jpg']);
    }

    public function color(string $slot): ?string
    {
        $palette = $this->themeColors() ?: config()->array('theme.presets.default.colors');

        $value = $palette[$slot] ?? null;

        return is_string($value) ? $value : null;
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
        $accentColor = $palette['accent'] ?? $palette['primary_bg'] ?? null;
        if ($accentColor !== null && isset($palette['primary_text'])) {
            $accent = [
                "--color-accent:{$accentColor}",
                "--color-accent-content:{$accentColor}",
                "--color-accent-foreground:{$palette['primary_text']}",
            ];
            $root = [...$root, ...$accent];
            $dark = $accent;
        }

        $radiusKey = (string) config('site.radius', '') ?: config()->string('theme.default_radius');
        $radius = config()->string("theme.radii.$radiusKey", '');
        if ($radius !== '') {
            $root[] = "--wire-radius:$radius";

            foreach (['sm', 'md', 'lg', 'xl'] as $size) {
                $root[] = "--radius-$size:$radius";
            }
        }

        $borderWidthKey = (string) config('site.border_width', '') ?: config()->string('theme.default_border_width');
        $borderWidth = config()->string("theme.border_widths.$borderWidthKey", '');
        if ($borderWidth !== '') {
            $root[] = "--wire-border-width:$borderWidth";
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

        $containerKey = (string) config('site.container', '') ?: config()->string('theme.default_container');
        $container = config()->string("theme.containers.$containerKey", '');
        if ($container !== '') {
            $root[] = "--wire-container:$container";
        }

        $root[] = '--wire-gutter:1.5rem';
        $fullGutter = $containerKey === 'full'
            ? match ($this->blockSpacing()) {
                'small' => '3rem',
                'large' => '5rem',
                default => '4rem',
            }
        : '';

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

        $css = ':root{'.implode(';', $root).'}'.($dark === [] ? '' : '.dark{'.implode(';', $dark).'}');

        if ($fullGutter !== '') {
            $css .= '@media(min-width:768px){:root{--wire-gutter:'.$fullGutter.'}}';
        }

        return $css;
    }

    public function customCss(): string
    {
        return mb_trim((string) config('site.custom_css', ''));
    }

    public function headScripts(): string
    {
        return mb_trim((string) config('site.head_scripts', ''));
    }

    public function bodyScripts(): string
    {
        return mb_trim((string) config('site.body_scripts', ''));
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
     * @return array<int, array{type: string, label: string, url: string, target: string, appearance: string, icon: ?string, badge: string, badgeColor: string}>
     */
    public function menu(string $key): array
    {
        $menu = collect($this->allMenus())->firstWhere('key', $key);

        if ($menu === null) {
            return [];
        }

        $items = $this->localeMenuItems($menu['items']);
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
     * @return array<int, array{key: string, name: string, builtin: bool, display: array{background: bool, position: string, sticky: bool, mobile: string}, items: array<string, array<int, mixed>>}>
     */
    public function allMenus(): array
    {
        return self::normalizeMenus(config('site.menus'));
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

    public function blockSpacing(): string
    {
        $value = (string) config('site.block_spacing', '');

        return array_key_exists($value, config()->array('theme.block_spacings'))
            ? $value
            : config()->string('theme.default_block_spacing');
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
    private function imageUrl(?array $item, string $crop, array $params): ?string
    {
        $source = $item['source'] ?? null;
        if (! is_string($source) || $source === '') {
            return null;
        }

        $crops = is_array($item['crop'] ?? null) ? $item['crop'] : [];
        $variant = is_array($crops[$crop] ?? null) ? $crops[$crop] : null;

        return route('image.show', [
            'options' => $variant !== null
                ? $this->cropString([...$variant, ...$params])
                : $this->scaleString($params),
            'path' => $source,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function scaleString(array $params): string
    {
        $values = [
            'w' => 1200,
            'h' => 800,
            'q' => 80,
            'fm' => 'jpg',
            ...Arr::only($params, ['w', 'h', 'q', 'fm']),
        ];

        return implode(',', [
            "w={$values['w']}",
            "h={$values['h']}",
            "q={$values['q']}",
            "fm={$values['fm']}",
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
     * @return array{type: string, label: string, url: string, target: string, appearance: string, icon: ?string, badge: string, badgeColor: string}|null
     */
    private function resolveMenuItem(array $item, Collection $pages): ?array
    {
        $label = is_string($item['label'] ?? null) ? $item['label'] : '';
        if ($label === '') {
            return null;
        }

        $type = $item['type'] ?? null;

        if ($type === 'heading') {
            return [
                'type' => 'heading',
                'label' => $label,
                'url' => '',
                'target' => '_self',
                'appearance' => 'link',
                'icon' => null,
                'badge' => '',
                'badgeColor' => 'zinc',
            ];
        }

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

        $icon = is_string($item['icon'] ?? null) ? $item['icon'] : '';
        $badgeColor = is_string($item['badgeColor'] ?? null) ? $item['badgeColor'] : 'zinc';

        return [
            'type' => 'link',
            'label' => $label,
            'url' => $url,
            'target' => ($item['target'] ?? null) === '_blank' ? '_blank' : '_self',
            'appearance' => ($item['appearance'] ?? null) === 'button' ? 'button' : 'link',
            'icon' => in_array($icon, config()->array('menu.icons'), true) ? $icon : null,
            'badge' => is_string($item['badge'] ?? null) ? $item['badge'] : '',
            'badgeColor' => in_array($badgeColor, config()->array('menu.badge_colors'), true) ? $badgeColor : 'zinc',
        ];
    }
}
