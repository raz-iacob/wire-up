<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateSettingsAction;
use App\Enums\MediaType;
use App\Mcp\Support\Pages;
use App\Mcp\Support\SiteSettings;
use App\Models\Media;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-design')]
#[Description('Update the site design tokens: theme, colors, fonts, sizes, shape, and header/footer layout. Pass only the settings to change; use get-settings for current values and the valid options.')]
final class UpdateDesignTool extends Tool
{
    public function handle(Request $request): Response
    {
        $options = SiteSettings::options();

        $validated = $request->validate(
            [
                'theme' => ['sometimes', 'string', Rule::in($options['themes'])],
                'theme_dark' => ['sometimes', 'string', Rule::in($options['dark_themes'])],
                'colors' => ['sometimes', 'array'],
                'colors.*' => ['string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'colors_dark' => ['sometimes', 'array'],
                'colors_dark.*' => ['string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'heading_font' => ['sometimes', 'string', Rule::in($options['fonts'])],
                'body_font' => ['sometimes', 'string', Rule::in($options['fonts'])],
                'heading_font_custom' => ['sometimes', 'string', 'max:60', 'regex:/^[A-Za-z0-9 ]+$/'],
                'body_font_custom' => ['sometimes', 'string', 'max:60', 'regex:/^[A-Za-z0-9 ]+$/'],
                'heading_size' => ['sometimes', 'string', Rule::in($options['heading_sizes'])],
                'body_size' => ['sometimes', 'string', Rule::in($options['body_sizes'])],
                'radius' => ['sometimes', 'string', Rule::in($options['radii'])],
                'border_width' => ['sometimes', 'string', Rule::in($options['border_widths'])],
                'container' => ['sometimes', 'string', Rule::in($options['containers'])],
                'block_spacing' => ['sometimes', 'string', Rule::in($options['block_spacings'])],
                'header_layout' => ['sometimes', 'string', Rule::in($options['header_layouts'])],
                'header_transparent' => ['sometimes', 'boolean'],
                'header_sticky' => ['sometimes', 'boolean'],
                'header_logo_size' => ['sometimes', 'string', Rule::in($options['element_sizes'])],
                'header_nav_size' => ['sometimes', 'string', Rule::in($options['element_sizes'])],
                'header_nav_hover' => ['sometimes', 'string', Rule::in($options['nav_hover_states'])],
                'footer_layout' => ['sometimes', 'string', Rule::in($options['footer_layouts'])],
                'footer_transparent' => ['sometimes', 'boolean'],
                'custom_css' => ['sometimes', 'string', 'max:50000'],
                'logo_header' => ['sometimes', 'integer'],
                'logo_footer' => ['sometimes', 'integer'],
            ],
            [
                'colors.*.regex' => 'Colors must be 6-digit hex values like #1a2b3c.',
                'colors_dark.*.regex' => 'Colors must be 6-digit hex values like #1a2b3c.',
                'heading_font_custom.regex' => 'Use only letters, numbers and spaces for the Google Font name.',
                'body_font_custom.regex' => 'Use only letters, numbers and spaces for the Google Font name.',
            ],
        );

        if ($validated === []) {
            return Response::error('Pass at least one design setting to change. Use get-settings for the current design and valid options.');
        }

        $error = $this->applyColors($validated, $options['color_slots'])
            ?? $this->checkCustomFonts($validated)
            ?? $this->applyLogos($validated);

        if ($error !== null) {
            return Response::error($error);
        }

        new UpdateSettingsAction()->handle($validated);

        return Pages::json([
            'design' => SiteSettings::design(),
            'hint' => 'Design tokens apply site-wide immediately.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $options = SiteSettings::options();

        return [
            'theme' => $schema->string()->enum($options['themes'])->description('Color theme preset, or "custom" to use the colors argument.'),
            'theme_dark' => $schema->string()->enum($options['dark_themes'])->description('Palette for visitors whose device prefers dark mode: "none" to disable, a preset, or "custom" with colors_dark.'),
            'colors' => $schema->object()->description('Custom palette keyed by slot (see options.color_slots in get-settings), hex values like "#1a2b3c". Requires theme "custom".'),
            'colors_dark' => $schema->object()->description('Custom dark-mode palette keyed by slot, hex values like "#1a2b3c". Requires theme_dark "custom".'),
            'heading_font' => $schema->string()->enum($options['fonts'])->description('Heading font, or "custom" with heading_font_custom.'),
            'body_font' => $schema->string()->enum($options['fonts'])->description('Body font, or "custom" with body_font_custom.'),
            'heading_font_custom' => $schema->string()->description('Google Font name used when heading_font is "custom".'),
            'body_font_custom' => $schema->string()->description('Google Font name used when body_font is "custom".'),
            'heading_size' => $schema->string()->enum($options['heading_sizes'])->description('Heading size scale.'),
            'body_size' => $schema->string()->enum($options['body_sizes'])->description('Body text size scale.'),
            'radius' => $schema->string()->enum($options['radii'])->description('Corner radius applied to cards, buttons, and inputs.'),
            'border_width' => $schema->string()->enum($options['border_widths'])->description('Border width used across the site.'),
            'container' => $schema->string()->enum($options['containers'])->description('Maximum content width.'),
            'block_spacing' => $schema->string()->enum($options['block_spacings'])->description('Vertical spacing between page blocks.'),
            'header_layout' => $schema->string()->enum($options['header_layouts'])->description('Header layout variant.'),
            'header_transparent' => $schema->boolean()->description('Overlay a transparent header on the first block.'),
            'header_sticky' => $schema->boolean()->description('Keep the header pinned while scrolling.'),
            'header_logo_size' => $schema->string()->enum($options['element_sizes'])->description('Header logo size.'),
            'header_nav_size' => $schema->string()->enum($options['element_sizes'])->description('Header navigation text size.'),
            'header_nav_hover' => $schema->string()->enum($options['nav_hover_states'])->description('Navigation hover effect.'),
            'footer_layout' => $schema->string()->enum($options['footer_layouts'])->description('Footer layout variant.'),
            'footer_transparent' => $schema->boolean()->description('Render the footer without a background.'),
            'custom_css' => $schema->string()->description('Extra CSS appended to the public site.'),
            'logo_header' => $schema->integer()->description('Media id of the header logo image.'),
            'logo_footer' => $schema->integer()->description('Media id of the footer logo image.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, string>  $slots
     */
    private function applyColors(array &$validated, array $slots): ?string
    {
        foreach (['theme' => 'colors', 'theme_dark' => 'colors_dark'] as $themeKey => $colorsKey) {
            if (isset($validated[$colorsKey])) {
                $unknown = array_diff(array_keys($validated[$colorsKey]), $slots);

                if ($unknown !== []) {
                    return 'Unknown color slot(s): '.implode(', ', $unknown).'. Valid slots: '.implode(', ', $slots).'.';
                }

                $current = is_array(config("site.$colorsKey")) ? config("site.$colorsKey") : [];
                $validated[$colorsKey] = [...$current, ...$validated[$colorsKey]];
            }

            $theme = $validated[$themeKey] ?? SiteSettings::design()[$themeKey];

            if ($theme === 'custom') {
                $colors = $validated[$colorsKey] ?? (is_array(config("site.$colorsKey")) ? config("site.$colorsKey") : []);
                $missing = array_diff($slots, array_keys($colors));

                if ($missing !== []) {
                    $label = $themeKey === 'theme' ? 'custom theme' : 'custom dark theme';

                    return "The $label needs a color for every slot. Missing: ".implode(', ', $missing).'.';
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function checkCustomFonts(array $validated): ?string
    {
        foreach (['heading', 'body'] as $kind) {
            $custom = (string) ($validated["{$kind}_font_custom"] ?? config("site.{$kind}_font_custom", ''));

            if (($validated["{$kind}_font"] ?? null) === 'custom' && mb_trim($custom) === '') {
                return "Set {$kind}_font_custom to a Google Font name when using a custom {$kind} font.";
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyLogos(array &$validated): ?string
    {
        foreach (['logo_header', 'logo_footer'] as $key) {
            if (! isset($validated[$key])) {
                continue;
            }

            $media = Media::query()->find($validated[$key]);

            if ($media === null) {
                return "No media with id {$validated[$key]}. Use list-media, or import a logo with import-media-from-url first.";
            }

            if ($media->type !== MediaType::IMAGE) {
                return "Media {$media->id} is a {$media->type->value}; logos must be images.";
            }

            $validated[$key] = ['id' => $media->id, 'source' => $media->source];
        }

        return null;
    }
}
