<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Services\SettingsService;

final readonly class SiteSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function identity(): array
    {
        $default = resolve('localization')->getDefaultLocale();
        $title = config('site.title');
        $description = config('site.description');

        return [
            'title' => is_array($title) ? $title : [$default => (string) $title],
            'description' => is_array($description) ? $description : [$default => (string) $description],
            'noindex' => (bool) config('site.noindex', false),
            'home_page_id' => SettingsService::current()->homePageId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function design(): array
    {
        $meta = is_array(config('site')) ? config('site') : [];

        $design = ['theme' => self::stringOr($meta, 'theme', 'theme.default')];

        foreach ([
            'heading_font' => 'theme.default_font',
            'body_font' => 'theme.default_font',
            'heading_size' => 'theme.default_heading_size',
            'body_size' => 'theme.default_body_size',
            'radius' => 'theme.default_radius',
            'border_width' => 'theme.default_border_width',
            'container' => 'theme.default_container',
            'block_spacing' => 'theme.default_block_spacing',
            'header_layout' => 'theme.default_header_layout',
            'header_logo_size' => 'theme.default_header_logo_size',
            'header_nav_size' => 'theme.default_header_nav_size',
            'header_nav_hover' => 'theme.default_header_nav_hover',
            'footer_layout' => 'theme.default_footer_layout',
        ] as $key => $configKey) {
            $design[$key] = self::stringOr($meta, $key, $configKey);
        }

        return [
            ...$design,
            'theme_dark' => is_string($meta['theme_dark'] ?? null) && $meta['theme_dark'] !== '' ? $meta['theme_dark'] : config()->string('theme.default_dark'),
            'colors' => is_array($meta['colors'] ?? null) ? $meta['colors'] : [],
            'colors_dark' => is_array($meta['colors_dark'] ?? null) ? $meta['colors_dark'] : [],
            'heading_font_custom' => is_string($meta['heading_font_custom'] ?? null) ? $meta['heading_font_custom'] : '',
            'body_font_custom' => is_string($meta['body_font_custom'] ?? null) ? $meta['body_font_custom'] : '',
            'header_transparent' => (bool) ($meta['header_transparent'] ?? false),
            'header_sticky' => (bool) ($meta['header_sticky'] ?? false),
            'footer_transparent' => (bool) ($meta['footer_transparent'] ?? false),
            'custom_css' => is_string($meta['custom_css'] ?? null) ? $meta['custom_css'] : '',
            'logo_header' => data_get($meta, 'logo_header.id'),
            'logo_footer' => data_get($meta, 'logo_footer.id'),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function options(): array
    {
        return [
            'themes' => [...array_keys(config()->array('theme.presets')), 'custom'],
            'dark_themes' => ['none', ...array_keys(config()->array('theme.presets')), 'custom'],
            'fonts' => [...array_keys(config()->array('theme.fonts')), 'custom'],
            'heading_sizes' => array_keys(config()->array('theme.heading_sizes')),
            'body_sizes' => array_keys(config()->array('theme.body_sizes')),
            'radii' => array_keys(config()->array('theme.radii')),
            'border_widths' => array_keys(config()->array('theme.border_widths')),
            'containers' => array_keys(config()->array('theme.containers')),
            'block_spacings' => array_keys(config()->array('theme.block_spacings')),
            'header_layouts' => array_keys(config()->array('theme.header_layouts')),
            'footer_layouts' => array_keys(config()->array('theme.footer_layouts')),
            'element_sizes' => array_keys(config()->array('theme.element_sizes')),
            'nav_hover_states' => array_keys(config()->array('theme.nav_hover_states')),
            'color_slots' => array_keys(config()->array('theme.slots')),
            'social_platforms' => array_map(strval(...), array_keys(config()->array('social.platforms'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function stringOr(array $meta, string $key, string $configKey): string
    {
        $value = $meta[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : config()->string($configKey);
    }
}
