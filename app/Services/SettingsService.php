<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

final class SettingsService
{
    /**
     * @var array<int, string>
     */
    public const array BUILTIN_MENUS = ['header', 'footer'];

    public const string AUTH_LINK_PREFIX = 'auth:';

    /**
     * @var array<string, string>
     */
    private const array REGION_CURRENCY = [
        'US' => 'USD', 'GB' => 'GBP', 'CA' => 'CAD', 'AU' => 'AUD', 'NZ' => 'NZD',
        'CH' => 'CHF', 'JP' => 'JPY', 'CN' => 'CNY', 'HK' => 'HKD', 'SG' => 'SGD',
        'IN' => 'INR', 'ID' => 'IDR', 'MY' => 'MYR', 'TH' => 'THB', 'VN' => 'VND',
        'KR' => 'KRW', 'PH' => 'PHP', 'TR' => 'TRY', 'RU' => 'RUB', 'UA' => 'UAH',
        'PL' => 'PLN', 'CZ' => 'CZK', 'HU' => 'HUF', 'RO' => 'RON', 'BG' => 'BGN',
        'DK' => 'DKK', 'NO' => 'NOK', 'SE' => 'SEK', 'IL' => 'ILS', 'SA' => 'SAR',
        'AE' => 'AED', 'ZA' => 'ZAR', 'MX' => 'MXN', 'BR' => 'BRL', 'NG' => 'NGN',
        'DE' => 'EUR', 'FR' => 'EUR', 'ES' => 'EUR', 'IT' => 'EUR', 'NL' => 'EUR',
        'PT' => 'EUR', 'GR' => 'EUR', 'FI' => 'EUR', 'IE' => 'EUR', 'AT' => 'EUR',
        'BE' => 'EUR', 'EE' => 'EUR', 'LV' => 'EUR', 'LT' => 'EUR', 'SK' => 'EUR',
        'SI' => 'EUR', 'HR' => 'EUR',
    ];

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

    public static function deduceCurrency(): string
    {
        $localization = resolve('localization');
        $regional = $localization->getActiveLocales()[$localization->getDefaultLocale()]['regional'] ?? null;
        $region = is_string($regional) ? str($regional)->after('-')->upper()->value() : '';

        return self::REGION_CURRENCY[$region] ?? 'USD';
    }

    /**
     * @return array{display: array{background: bool, position: string, sticky: bool, mobile: string}, items: array<int, array{type: string, label: string, url: string, target: string, appearance: string, icon: ?string, icon_svg: string, badge: string, badgeColor: string}>}|null
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
        $configured = Settings::get('home_page_id');

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

    public function allowsRegistration(): bool
    {
        return (bool) config('site.allow_registration', false);
    }

    /**
     * @return array<string, string>
     */
    public function authPageOptions(): array
    {
        $options = ['auth:login' => __('Login')];

        if ($this->allowsRegistration()) {
            $options['auth:register'] = __('Register');
        }

        return $options;
    }

    public function resolveAuthLink(string $value): ?string
    {
        return match ($value) {
            'auth:login' => route('login'),
            'auth:register' => $this->allowsRegistration() ? route('register') : null,
            default => null,
        };
    }

    public function authLayout(): string
    {
        $layout = config('site.auth_layout');

        if (is_string($layout) && config()->has('theme.auth_layouts.'.$layout)) {
            return $layout;
        }

        return config()->string('theme.default_auth_layout');
    }

    public function authImageUrl(string $crop = 'default'): ?string
    {
        $item = config('site.auth_image');

        return $this->imageUrl(is_array($item) ? $item : null, $crop, ['w' => 1200, 'q' => 80]);
    }

    public function authImageSide(): string
    {
        return config('site.auth_image_side') === 'right' ? 'right' : 'left';
    }

    public function currency(): string
    {
        $code = config('site.currency');

        if (is_string($code) && $code !== '' && config()->has("currencies.$code")) {
            return $code;
        }

        return self::deduceCurrency();
    }

    public function currencySymbol(): string
    {
        return config()->string('currencies.'.$this->currency().'.symbol', $this->currency());
    }

    public function currencyDecimals(): int
    {
        return config()->integer('currencies.'.$this->currency().'.decimals', 2);
    }

    public function formatMoney(int|float|string|null $amount): string
    {
        if (! is_numeric($amount)) {
            return '';
        }

        return $this->currencySymbol().number_format((float) $amount, $this->currencyDecimals(), '.', ',');
    }

    public function googleAnalyticsId(): string
    {
        $id = config('site.google_analytics_id');

        return is_string($id) ? $id : '';
    }

    public function googleMapsApiKey(): string
    {
        $key = config('site.google_maps_api_key');

        return is_string($key) ? $key : '';
    }

    public function brandName(): string
    {
        $title = $this->title();

        return $title !== '' ? $title : config()->string('app.name');
    }

    /**
     * @return array{url: string, height: int}|null
     */
    public function mailLogo(): ?array
    {
        $source = config('site.logo_header.source');

        if (! is_string($source)) {
            return ['url' => asset('images/wire-up-mail-logo.png'), 'height' => 32];
        }

        if (str_ends_with(mb_strtolower($source), '.svg')) {
            return null;
        }

        $url = $this->logoUrl('logo_header', maxHeight: 100);

        return $url === null ? null : ['url' => $url, 'height' => 50];
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

        return ImageService::url(implode(',', $options), $source);
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
        return $this->paletteFromTheme((string) config('site.theme', ''), 'site.colors');
    }

    /**
     * @return array<string, string>
     */
    public function darkThemeColors(): array
    {
        $theme = (string) config('site.theme_dark', '');

        return $this->paletteFromTheme($theme === '' ? config()->string('theme.default_dark') : $theme, 'site.colors_dark');
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

        $root = $this->colorDeclarations($palette);

        $dark = [];
        $accent = $this->accentDeclarations($palette);
        if ($accent !== []) {
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

        $headingStack = $this->fontFor('heading')['stack'];
        $bodyStack = $this->fontFor('body')['stack'];
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

        $darkPalette = $this->darkThemeColors();
        if ($darkPalette !== []) {
            $darkVars = implode(';', [...$this->colorDeclarations($darkPalette), ...$this->accentDeclarations($darkPalette)]);
            $css .= ':root.dark{'.$darkVars.'}';
            $css .= '@media(prefers-color-scheme:dark){:root:where(:not(.light)){'.$darkVars.'}}';
        }

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
        foreach (['heading', 'body'] as $slot) {
            $key = (string) config("site.{$slot}_font", '');

            if ($key === '') {
                continue;
            }

            $family = $key === 'custom'
                ? mb_trim((string) config("site.{$slot}_font_custom", ''))
                : config()->string("theme.fonts.$key.google", '');

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
     * @return array<int, array{type: string, label: string, url: string, target: string, appearance: string, icon: ?string, icon_svg: string, badge: string, badgeColor: string}>
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

    /**
     * @return array<string, string>
     */
    private function paletteFromTheme(string $theme, string $customConfigKey): array
    {
        if ($theme === '' || $theme === 'none') {
            return [];
        }

        $colors = $theme === 'custom'
            ? config($customConfigKey, [])
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

    /**
     * @param  array<string, string>  $palette
     * @return list<string>
     */
    private function colorDeclarations(array $palette): array
    {
        $declarations = [];
        foreach ($palette as $slot => $hex) {
            $name = match ($slot) {
                'background' => 'body-bg',
                'text' => 'body-text',
                default => str_replace('_', '-', $slot),
            };
            $declarations[] = "--wire-$name:$hex";
        }

        return $declarations;
    }

    /**
     * @param  array<string, string>  $palette
     * @return list<string>
     */
    private function accentDeclarations(array $palette): array
    {
        $accentColor = $palette['accent'] ?? $palette['primary_bg'] ?? null;

        if ($accentColor === null || ! isset($palette['primary_text'])) {
            return [];
        }

        return [
            "--color-accent:{$accentColor}",
            "--color-accent-content:{$accentColor}",
            "--color-accent-foreground:{$palette['primary_text']}",
        ];
    }

    /**
     * @return array{stack: string, google: string}
     */
    private function fontFor(string $slot): array
    {
        $key = (string) config("site.{$slot}_font", '') ?: config()->string('theme.default_font');

        if ($key === 'custom') {
            $custom = mb_trim((string) config("site.{$slot}_font_custom", ''));

            return $custom === ''
                ? ['stack' => '', 'google' => '']
                : ['stack' => '"'.$custom.'", sans-serif', 'google' => $custom];
        }

        return [
            'stack' => config()->string("theme.fonts.$key.stack", ''),
            'google' => config()->string("theme.fonts.$key.google", ''),
        ];
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

        return ImageService::url(
            $variant !== null ? $this->cropString([...$variant, ...$params]) : $this->scaleString($params),
            $source,
        );
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
     * @return array{type: string, label: string, url: string, target: string, appearance: string, icon: ?string, icon_svg: string, badge: string, badgeColor: string}|null
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
                'icon_svg' => '',
                'badge' => '',
                'badgeColor' => 'zinc',
            ];
        }

        $url = null;

        if ($type === 'link') {
            $url = is_string($item['url'] ?? null) && $item['url'] !== '' ? $item['url'] : null;
        } elseif ($type === 'page') {
            $raw = is_scalar($item['page_id'] ?? null) ? (string) $item['page_id'] : '';

            if (str_starts_with($raw, self::AUTH_LINK_PREFIX)) {
                $url = $this->resolveAuthLink($raw);
            } else {
                $page = $raw !== '' ? $pages->get((int) $raw) : null;
                if ($page instanceof Page && $page->slug !== '') {
                    $url = route('page', $page->slug);
                }
            }
        }

        if ($url === null) {
            return null;
        }

        $icon = is_string($item['icon'] ?? null) ? $item['icon'] : '';
        $badgeColor = is_string($item['badgeColor'] ?? null) ? $item['badgeColor'] : 'zinc';
        $iconSvg = is_string($item['icon_svg'] ?? null) ? $item['icon_svg'] : '';
        $appearance = in_array($item['appearance'] ?? null, ['button', 'icon'], true) ? (string) $item['appearance'] : 'link';

        if ($appearance === 'icon' && $iconSvg === '') {
            $appearance = 'link';
        }

        return [
            'type' => 'link',
            'label' => $label,
            'url' => $url,
            'target' => ($item['target'] ?? null) === '_blank' ? '_blank' : '_self',
            'appearance' => $appearance,
            'icon' => in_array($icon, config()->array('menu.icons'), true) ? $icon : null,
            'icon_svg' => $appearance === 'icon' ? $iconSvg : '',
            'badge' => is_string($item['badge'] ?? null) ? $item['badge'] : '',
            'badgeColor' => in_array($badgeColor, config()->array('menu.badge_colors'), true) ? $badgeColor : 'zinc',
        ];
    }
}
