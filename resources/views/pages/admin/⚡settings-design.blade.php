<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Component;

return new class extends Component
{
    public string $theme;

    /**
     * @var array<string, string>
     */
    public array $colors = [];

    public string $theme_dark;

    /**
     * @var array<string, string>
     */
    public array $colors_dark = [];

    public string $heading_font;

    public string $body_font;

    public string $heading_font_custom = '';

    public string $body_font_custom = '';

    public string $heading_size;

    public string $body_size;

    public string $radius;

    public string $border_width;

    public string $container;

    public string $block_spacing;

    public string $header_layout;

    public bool $header_transparent = false;

    public bool $header_sticky = false;

    public bool $header_theme_toggle = false;

    public string $header_logo_size;

    public string $header_nav_size;

    public string $header_nav_hover;

    public string $footer_layout;

    public bool $footer_transparent = false;

    public string $auth_layout;

    public string $auth_image_side;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $auth_image = null;

    public string $custom_css = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $logo_header = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $logo_footer = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $logo_header_dark = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $logo_footer_dark = null;

    public function mount(): void
    {
        /** @var array<string, mixed> $meta */
        $meta = is_array(config('site')) ? config('site') : [];

        $this->theme = is_string($meta['theme'] ?? null) ? $meta['theme'] : config()->string('theme.default');
        $this->colors = $this->resolvePalette($this->theme, $meta);
        $this->theme_dark = is_string($meta['theme_dark'] ?? null) ? $meta['theme_dark'] : config()->string('theme.default_dark');
        $this->colors_dark = $this->resolveDarkPalette($this->theme_dark, $meta);
        $this->heading_font = $meta['heading_font'] ?? config()->string('theme.default_font');
        $this->body_font = $meta['body_font'] ?? config()->string('theme.default_font');
        $this->heading_font_custom = $meta['heading_font_custom'] ?? '';
        $this->body_font_custom = $meta['body_font_custom'] ?? '';
        $this->heading_size = $meta['heading_size'] ?? config()->string('theme.default_heading_size');
        $this->body_size = $meta['body_size'] ?? config()->string('theme.default_body_size');
        $this->radius = $meta['radius'] ?? config()->string('theme.default_radius');
        $this->border_width = is_string($meta['border_width'] ?? null) ? $meta['border_width'] : config()->string('theme.default_border_width');
        $this->container = is_string($meta['container'] ?? null) ? $meta['container'] : config()->string('theme.default_container');
        $this->block_spacing = is_string($meta['block_spacing'] ?? null) ? $meta['block_spacing'] : config()->string('theme.default_block_spacing');
        $this->header_layout = is_string($meta['header_layout'] ?? null) ? $meta['header_layout'] : config()->string('theme.default_header_layout');
        $this->header_transparent = (bool) ($meta['header_transparent'] ?? false);
        $this->header_sticky = (bool) ($meta['header_sticky'] ?? false);
        $this->header_theme_toggle = (bool) ($meta['header_theme_toggle'] ?? false);
        $this->header_logo_size = is_string($meta['header_logo_size'] ?? null) ? $meta['header_logo_size'] : config()->string('theme.default_header_logo_size');
        $this->header_nav_size = is_string($meta['header_nav_size'] ?? null) ? $meta['header_nav_size'] : config()->string('theme.default_header_nav_size');
        $this->header_nav_hover = is_string($meta['header_nav_hover'] ?? null) ? $meta['header_nav_hover'] : config()->string('theme.default_header_nav_hover');
        $this->footer_layout = is_string($meta['footer_layout'] ?? null) ? $meta['footer_layout'] : config()->string('theme.default_footer_layout');
        $this->footer_transparent = (bool) ($meta['footer_transparent'] ?? false);
        $this->auth_layout = is_string($meta['auth_layout'] ?? null) ? $meta['auth_layout'] : config()->string('theme.default_auth_layout');
        $this->auth_image_side = ($meta['auth_image_side'] ?? null) === 'right' ? 'right' : 'left';
        $this->custom_css = is_string($meta['custom_css'] ?? null) ? $meta['custom_css'] : '';

        $this->logo_header = is_array($meta['logo_header'] ?? null) ? $meta['logo_header'] : null;
        $this->logo_footer = is_array($meta['logo_footer'] ?? null) ? $meta['logo_footer'] : null;
        $this->logo_header_dark = is_array($meta['logo_header_dark'] ?? null) ? $meta['logo_header_dark'] : null;
        $this->logo_footer_dark = is_array($meta['logo_footer_dark'] ?? null) ? $meta['logo_footer_dark'] : null;
        $this->auth_image = is_array($meta['auth_image'] ?? null) ? $meta['auth_image'] : null;
    }

    public function update(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $rules = [
            'theme' => ['required', 'string', Rule::in([...array_keys(config()->array('theme.presets')), 'custom'])],
            'theme_dark' => ['required', 'string', Rule::in(['none', ...array_keys(config()->array('theme.presets')), 'custom'])],
            'heading_font' => ['required', 'string', Rule::in([...array_keys(config()->array('theme.fonts')), 'custom'])],
            'body_font' => ['required', 'string', Rule::in([...array_keys(config()->array('theme.fonts')), 'custom'])],
            'heading_font_custom' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 ]+$/', 'required_if:heading_font,custom'],
            'body_font_custom' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 ]+$/', 'required_if:body_font,custom'],
            'heading_size' => ['required', 'string', Rule::in(array_keys(config()->array('theme.heading_sizes')))],
            'body_size' => ['required', 'string', Rule::in(array_keys(config()->array('theme.body_sizes')))],
            'radius' => ['required', 'string', Rule::in(array_keys(config()->array('theme.radii')))],
            'border_width' => ['required', 'string', Rule::in(array_keys(config()->array('theme.border_widths')))],
            'container' => ['required', 'string', Rule::in(array_keys(config()->array('theme.containers')))],
            'block_spacing' => ['required', 'string', Rule::in(array_keys(config()->array('theme.block_spacings')))],
            'header_layout' => ['required', 'string', Rule::in(array_keys(config()->array('theme.header_layouts')))],
            'header_transparent' => ['boolean'],
            'header_sticky' => ['boolean'],
            'header_theme_toggle' => ['boolean'],
            'header_logo_size' => ['required', 'string', Rule::in(array_keys(config()->array('theme.element_sizes')))],
            'header_nav_size' => ['required', 'string', Rule::in(array_keys(config()->array('theme.element_sizes')))],
            'header_nav_hover' => ['required', 'string', Rule::in(array_keys(config()->array('theme.nav_hover_states')))],
            'footer_layout' => ['required', 'string', Rule::in(array_keys(config()->array('theme.footer_layouts')))],
            'footer_transparent' => ['boolean'],
            'auth_layout' => ['required', 'string', Rule::in(array_keys(config()->array('theme.auth_layouts')))],
            'auth_image_side' => ['required', 'string', Rule::in(['left', 'right'])],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'logo_header' => ['nullable', 'array'],
            'logo_header.id' => ['nullable', 'integer', 'exists:media,id'],
            'logo_footer' => ['nullable', 'array'],
            'logo_footer.id' => ['nullable', 'integer', 'exists:media,id'],
            'logo_header_dark' => ['nullable', 'array'],
            'logo_header_dark.id' => ['nullable', 'integer', 'exists:media,id'],
            'logo_footer_dark' => ['nullable', 'array'],
            'logo_footer_dark.id' => ['nullable', 'integer', 'exists:media,id'],
            'auth_image' => ['nullable', 'array'],
            'auth_image.id' => ['nullable', 'integer', 'exists:media,id'],
        ];

        foreach (array_keys(config()->array('theme.slots')) as $slot) {
            $rules["colors.$slot"] = ['required_if:theme,custom', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
            $rules["colors_dark.$slot"] = ['required_if:theme_dark,custom', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        $validated = $this->validate($rules, [
            'heading_font_custom.required_if' => __('Enter the Google font name for the heading.'),
            'body_font_custom.required_if' => __('Enter the Google font name for the body.'),
            'heading_font_custom.regex' => __('Use only letters, numbers and spaces for the font name.'),
            'body_font_custom.regex' => __('Use only letters, numbers and spaces for the font name.'),
        ]);

        $metadata = Arr::only($validated, ['theme', 'theme_dark', 'heading_font', 'body_font', 'heading_font_custom', 'body_font_custom', 'heading_size', 'body_size', 'radius', 'border_width', 'container', 'block_spacing', 'header_layout', 'header_transparent', 'header_sticky', 'header_theme_toggle', 'header_logo_size', 'header_nav_size', 'header_nav_hover', 'footer_layout', 'footer_transparent', 'auth_layout', 'auth_image_side']);
        $metadata['heading_font_custom'] = mb_trim((string) ($validated['heading_font_custom'] ?? ''));
        $metadata['body_font_custom'] = mb_trim((string) ($validated['body_font_custom'] ?? ''));
        $metadata['custom_css'] = mb_trim((string) ($validated['custom_css'] ?? ''));

        if ($this->theme === 'custom') {
            $metadata['colors'] = Arr::only($this->colors, array_keys(config()->array('theme.slots')));
        }

        if ($this->theme_dark === 'custom') {
            $metadata['colors_dark'] = Arr::only($this->colors_dark, array_keys(config()->array('theme.slots')));
        }

        $action->handle([
            ...$metadata,
            'logo_header' => $this->logo_header,
            'logo_footer' => $this->logo_footer,
            'logo_header_dark' => $this->logo_header_dark,
            'logo_footer_dark' => $this->logo_footer_dark,
            'auth_image' => $this->auth_image,
        ]);

        Flux::toast(__('Design has been updated.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Design'))
            ->layout('layouts::admin');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function resolvePalette(string $theme, array $meta): array
    {
        if (is_array($meta['colors'] ?? null) && $this->onlyStringColors($meta['colors']) !== []) {
            return $this->onlyStringColors($meta['colors']);
        }

        return $this->presetColors($theme === 'custom' ? config()->string('theme.default') : $theme);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function resolveDarkPalette(string $theme, array $meta): array
    {
        if (is_array($meta['colors_dark'] ?? null) && $this->onlyStringColors($meta['colors_dark']) !== []) {
            return $this->onlyStringColors($meta['colors_dark']);
        }

        return $this->presetColors(in_array($theme, ['custom', 'none'], true) ? config()->string('theme.default_dark') : $theme);
    }

    /**
     * @return array<string, string>
     */
    private function presetColors(string $preset): array
    {
        return $this->onlyStringColors(config()->array("theme.presets.$preset.colors", []));
    }

    /**
     * @param  array<mixed>  $colors
     * @return array<string, string>
     */
    private function onlyStringColors(array $colors): array
    {
        $palette = [];
        foreach ($colors as $slot => $hex) {
            if (is_string($slot) && is_string($hex)) {
                $palette[$slot] = $hex;
            }
        }

        return $palette;
    }
};
?>

@php
    $presets = config('theme.presets');
    $fonts = config('theme.fonts');
    $fontStacks = collect($fonts)->map(fn (array $f): string => $f['stack'])->all();
    $brand = \App\Services\SettingsService::current()->title() ?: config('app.name');

    $slotsByGroup = [];
    foreach (config('theme.slots') as $slot => $def) {
        $slotsByGroup[$def['group']][$slot] = $def;
    }

    $googleFamilies = collect($fonts)->pluck('google')->filter()->unique()->values();
    $previewFontsUrl = $googleFamilies->isEmpty() ? null : 'https://fonts.googleapis.com/css2?'
        .$googleFamilies->map(fn (string $f): string => 'family='.str_replace(' ', '+', $f))->implode('&')
        .'&display=swap';
@endphp

<x-admin.settings-layout>
    @if ($previewFontsUrl)
        <link id="design-preview-fonts" rel="stylesheet" href="{{ $previewFontsUrl }}" />
    @endif
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="grid md:grid-cols-5 gap-10 items-start"
        x-data="{
            fonts: @js($fontStacks),
            headingSizes: @js(config('theme.heading_sizes')),
            bodySizes: @js(config('theme.body_sizes')),
            radii: @js(config('theme.radii')),
            borderWidths: @js(config('theme.border_widths')),
            presetPalettes: @js(collect($presets)->map(fn (array $p): array => $p['colors'])),
            previewDark: false,
            get c() {
                if (this.previewDark && $wire.theme_dark !== 'none') {
                    return ($wire.theme_dark !== 'custom' && this.presetPalettes[$wire.theme_dark])
                        ? this.presetPalettes[$wire.theme_dark]
                        : ($wire.colors_dark || {})
                }

                return ($wire.theme !== 'custom' && this.presetPalettes[$wire.theme])
                    ? this.presetPalettes[$wire.theme]
                    : ($wire.colors || {})
            },
            customStack(name) { return name && name.trim() ? '\'' + name.trim() + '\', sans-serif' : 'sans-serif' },
            get headingFont() { return $wire.heading_font === 'custom' ? this.customStack($wire.heading_font_custom) : (this.fonts[$wire.heading_font] || 'sans-serif') },
            get bodyFont() { return $wire.body_font === 'custom' ? this.customStack($wire.body_font_custom) : (this.fonts[$wire.body_font] || 'sans-serif') },
            get headingSize() { return (parseFloat(this.headingSizes[$wire.heading_size] || '1.5rem') * 0.6).toFixed(3) + 'rem' },
            get bodySize() { return (parseFloat(this.bodySizes[$wire.body_size] || '0.875rem') * 0.6).toFixed(3) + 'rem' },
            get radius() { return this.radii[$wire.radius] || '0.5rem' },
            get borderWidth() { return this.borderWidths[$wire.border_width] || '1px' },
            get headerBg() { return $wire.header_transparent ? 'transparent' : (this.c.header_bg || '') },
            get footerBg() { return $wire.footer_transparent ? 'transparent' : (this.c.footer_bg || '') },
            logoSrc(logo) {
                if (! logo) { return null }
                const crop = logo.crop?.default || Object.values(logo.crop || {})[0]
                if (crop && logo.source) {
                    const opts = `w=${crop.w ?? 480},h=${crop.h ?? 160},crop=${crop.crop_w ?? 0}-${crop.crop_h ?? 0}-${crop.crop_x ?? 0}-${crop.crop_y ?? 0},q=${crop.q ?? 80},fm=${crop.fm ?? 'jpg'}`
                    return `/img/${opts}/${logo.source}`
                }
                return logo.preview
            },
            get logoPreviewClass() { return (({ sm: 'h-3', md: 'h-4', lg: 'h-6' })[$wire.header_logo_size] || 'h-4') + ' w-auto object-contain' },
            get navPreviewSize() { return ({ sm: 'text-[9px]', md: 'text-[10px]', lg: 'text-[11px]' })[$wire.header_nav_size] || 'text-[10px]' },
            get copyrightSize() { return (parseFloat(this.bodySize) * 0.75).toFixed(3) + 'rem' },
        }"
        x-effect="
            const fams = new Set();
            if ($wire.heading_font === 'custom' && $wire.heading_font_custom.trim()) fams.add($wire.heading_font_custom.trim());
            if ($wire.body_font === 'custom' && $wire.body_font_custom.trim()) fams.add($wire.body_font_custom.trim());
            let link = document.getElementById('design-preview-custom-fonts');
            if (! fams.size) { if (link) link.remove(); }
            else {
                const href = 'https://fonts.googleapis.com/css2?' + [...fams].map(f => 'family=' + f.replace(/ +/g, '+')).join('&') + '&display=swap';
                if (! link) { link = document.createElement('link'); link.id = 'design-preview-custom-fonts'; link.rel = 'stylesheet'; document.head.appendChild(link); }
                if (link.getAttribute('href') !== href) link.setAttribute('href', href);
            }
        ">

        <div class="order-2 md:col-span-2 md:sticky md:top-4">
            <flux:text class="mb-6">{{ __('Design the look of your public site — colours, fonts, and shape.') }}</flux:text>
            <div class="mb-2 flex items-center justify-end gap-1" x-cloak x-show="$wire.theme_dark !== 'none'">
                <flux:button size="sm" variant="subtle" icon="sun" data-test="preview-scheme-light" x-on:click="previewDark = false" x-bind:data-active="! previewDark" class="data-[active=true]:text-accent" :tooltip="__('Light preview')" />
                <flux:button size="sm" variant="subtle" icon="moon" data-test="preview-scheme-dark" x-on:click="previewDark = true" x-bind:data-active="previewDark" class="data-[active=true]:text-accent" :tooltip="__('Dark preview')" />
            </div>
            <div x-cloak class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 select-none">

                <div class="relative transition-shadow" :class="$wire.header_sticky ? 'shadow-md' : ''">
                    <div data-test="header-variant" x-show="$wire.header_layout === 'simple'" class="flex items-center justify-between gap-4 px-4 py-3" :style="{ background: headerBg, color: c.header_text, fontFamily: headingFont }">
                        <span class="flex items-center gap-2">
                            <img x-cloak x-show="$wire.logo_header?.preview" :src="logoSrc($wire.logo_header)" alt="{{ $brand }}" :class="logoPreviewClass" />
                            <span x-show="! $wire.logo_header?.preview" class="font-bold text-sm">{{ $brand }}</span>
                        </span>
                        <div :class="navPreviewSize" class="flex items-center gap-3" :style="`font-family:${bodyFont}`">
                            <span>{{ __('Home') }}</span>
                            <span>{{ __('About') }}</span>
                            <span x-cloak x-show="$wire.header_theme_toggle && $wire.theme_dark !== 'none'" class="inline-flex items-center" data-test="preview-theme-toggle">
                                <flux:icon.moon x-show="!previewDark" class="size-4" />
                                <flux:icon.sun x-show="previewDark" class="size-4" />
                            </span>
                            <span class="px-2.5 py-1 font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border:${borderWidth} solid ${c.primary_border}; border-radius:${radius}`">{{ __('Sign up') }}</span>
                        </div>
                    </div>
                    <div data-test="header-variant" x-show="$wire.header_layout === 'centered'" class="px-4 py-3 text-center" :style="{ background: headerBg, color: c.header_text, fontFamily: headingFont }">
                        <div class="flex justify-center">
                            <img x-cloak x-show="$wire.logo_header?.preview" :src="logoSrc($wire.logo_header)" alt="{{ $brand }}" :class="logoPreviewClass" />
                            <span x-show="! $wire.logo_header?.preview" class="font-bold text-sm">{{ $brand }}</span>
                        </div>
                        <div :class="navPreviewSize" class="flex items-center justify-center gap-4 mt-2" :style="`font-family:${bodyFont}`">
                            <span>{{ __('Home') }}</span>
                            <span>{{ __('About') }}</span>
                            <span>{{ __('Pricing') }}</span>
                            <span x-cloak x-show="$wire.header_theme_toggle && $wire.theme_dark !== 'none'" class="inline-flex items-center" data-test="preview-theme-toggle">
                                <flux:icon.moon x-show="!previewDark" class="size-4" />
                                <flux:icon.sun x-show="previewDark" class="size-4" />
                            </span>
                            <span class="px-2.5 py-1 font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border:${borderWidth} solid ${c.primary_border}; border-radius:${radius}`">{{ __('Sign up') }}</span>
                        </div>
                    </div>
                    <div data-test="header-variant" x-show="$wire.header_layout === 'split'" class="grid grid-cols-3 items-center gap-2 px-4 py-3" :style="{ background: headerBg, color: c.header_text, fontFamily: headingFont }">
                        <span class="flex items-center gap-2">
                            <img x-cloak x-show="$wire.logo_header?.preview" :src="logoSrc($wire.logo_header)" alt="{{ $brand }}" :class="logoPreviewClass" />
                            <span x-show="! $wire.logo_header?.preview" class="font-bold text-sm">{{ $brand }}</span>
                        </span>
                        <div :class="navPreviewSize" class="flex items-center justify-center gap-4" :style="`font-family:${bodyFont}`">
                            <span>{{ __('Home') }}</span>
                            <span>{{ __('About') }}</span>
                            <span>{{ __('Pricing') }}</span>
                        </div>
                        <div class="flex items-center justify-end gap-2">
                            <span x-cloak x-show="$wire.header_theme_toggle && $wire.theme_dark !== 'none'" class="inline-flex items-center" data-test="preview-theme-toggle">
                                <flux:icon.moon x-show="!previewDark" class="size-4" />
                                <flux:icon.sun x-show="previewDark" class="size-4" />
                            </span>
                            <span class="px-2.5 py-1 text-xs font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border:${borderWidth} solid ${c.primary_border}; border-radius:${radius}`">{{ __('Sign up') }}</span>
                        </div>
                    </div>
                    <div data-test="header-variant" x-show="$wire.header_layout === 'minimal'" class="flex items-center justify-between px-4 py-3" :style="{ background: headerBg, color: c.header_text, fontFamily: headingFont }">
                        <span class="flex items-center gap-2">
                            <img x-cloak x-show="$wire.logo_header?.preview" :src="logoSrc($wire.logo_header)" alt="{{ $brand }}" :class="logoPreviewClass" />
                            <span x-show="! $wire.logo_header?.preview" class="font-bold text-sm">{{ $brand }}</span>
                        </span>
                        <div class="flex items-center gap-1.5">
                            <span class="h-0.5 w-4 rounded-full" :style="`background:${c.header_text}`"></span>
                            <span class="h-0.5 w-3 rounded-full" :style="`background:${c.header_text}`"></span>
                            <span class="h-0.5 w-2 rounded-full" :style="`background:${c.header_text}`"></span>
                        </div>
                    </div>
                    <div x-cloak x-show="$wire.header_sticky" class="absolute top-1 right-1 rounded px-1.5 py-0.5 text-[0.55rem] font-medium bg-zinc-800/70 text-white">{{ __('Sticky') }}</div>
                </div>

                <div data-test="preview-body" class="px-4 py-6" :style="`background:${c.background}; color:${c.text}`">
                    <div class="space-y-3 text-center">
                        <h1 class="font-bold" :style="`font-family:${headingFont}; font-size:${headingSize}`">{{ __('Build something great') }}</h1>
                        <p class="mx-auto max-w-xs" :style="`color:${c.muted}; font-family:${bodyFont}; font-size:${bodySize}`">{{ __('A clean starting point for your next project, themed to your brand.') }}</p>
                        <div class="flex items-center justify-center gap-2 pt-2" :style="`font-family:${bodyFont}`">
                            <span class="px-2 py-1 text-[10px] font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border:${borderWidth} solid ${c.primary_border}; border-radius:${radius}`">{{ __('Get started') }}</span>
                            <span class="px-2 py-1 text-[10px] font-medium" :style="`background:${c.secondary_bg}; color:${c.secondary_text}; border:${borderWidth} solid ${c.secondary_border}; border-radius:${radius}`">{{ __('Learn more') }}</span>
                        </div>
                        <div class="mx-auto flex max-w-xs items-center gap-2 pt-2" :style="`font-family:${bodyFont}`">
                            <span class="flex-1 px-2 py-1 text-left text-[10px]" :style="`background:${c.input_bg}; color:${c.input_text}; border:${borderWidth} solid ${c.input_border}; border-radius:${radius}`">name@email.com</span>
                            <span class="px-2 py-1 text-[10px] font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border:${borderWidth} solid ${c.primary_border}; border-radius:${radius}`">{{ __('Subscribe') }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3 pt-6">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="space-y-1.5 p-3 text-left" :style="`background:${c.card_bg}; border:${borderWidth} solid ${c.card_border}; border-radius:${radius}`">
                                <div class="text-[0.65rem] font-semibold" :style="`color:${c.card_text}; font-family:${headingFont}`">{{ __('Card title') }}</div>
                                <div class="text-[0.6rem] leading-snug" :style="`color:${c.card_text}; font-family:${bodyFont}`">{{ __('A short supporting line of text.') }}</div>
                            </div>
                        @endfor
                    </div>
                </div>

                <div data-test="footer-variant" x-show="$wire.footer_layout === 'simple'" :style="{ background: footerBg, color: c.footer_text, fontFamily: bodyFont }">
                    <div class="flex items-start justify-between gap-4 px-4 py-4 text-[10px]">
                        <div class="space-y-2">
                            <span class="flex items-center gap-2">
                                <img x-cloak x-show="$wire.logo_footer?.preview" :src="logoSrc($wire.logo_footer)" alt="{{ $brand }}" class="h-4 w-auto object-contain" />
                                <span x-show="! $wire.logo_footer?.preview" class="text-sm font-semibold">{{ $brand }}</span>
                            </span>
                            <div class="flex items-center gap-2 opacity-70">
                                @foreach (['facebook', 'x-twitter', 'instagram'] as $exIcon)
                                    <span class="size-3 bg-current mask-center mask-no-repeat mask-contain" style="mask-image:url('{{ Vite::asset("resources/images/socials/{$exIcon}-solid.svg") }}'); -webkit-mask-image:url('{{ Vite::asset("resources/images/socials/{$exIcon}-solid.svg") }}');"></span>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex items-center gap-4 opacity-80">
                            <span>{{ __('Services') }}</span>
                            <span>{{ __('About') }}</span>
                            <span>{{ __('Contact') }}</span>
                        </div>
                    </div>
                    <div :style="`font-size:${copyrightSize}`" class="flex items-center justify-between border-t border-current/10 px-4 py-2.5 opacity-60">
                        <span>&copy; {{ now()->year }} {{ $brand }}, {{ __('All Rights Reserved') }}</span>
                        <span>{{ __('Made with Wire-Up') }}</span>
                    </div>
                </div>
                <div data-test="footer-variant" x-show="$wire.footer_layout === 'centered'" :style="{ background: footerBg, color: c.footer_text, fontFamily: bodyFont }">
                    <div class="px-4 py-5 text-center text-xs space-y-3">
                        <div class="flex justify-center">
                            <img x-cloak x-show="$wire.logo_footer?.preview" :src="logoSrc($wire.logo_footer)" alt="{{ $brand }}" class="h-4 w-auto object-contain" />
                            <span x-show="! $wire.logo_footer?.preview" class="font-semibold">{{ $brand }}</span>
                        </div>
                        <div class="flex items-center justify-center gap-4 opacity-80">
                            <span>{{ __('Privacy') }}</span>
                            <span>{{ __('Terms') }}</span>
                            <span>{{ __('Contact') }}</span>
                        </div>
                        <div class="flex items-center justify-center gap-2">
                            @foreach (['facebook', 'x-twitter', 'instagram'] as $exIcon)
                                <span class="size-3 bg-current mask-center mask-no-repeat mask-contain" style="mask-image:url('{{ Vite::asset("resources/images/socials/{$exIcon}-solid.svg") }}'); -webkit-mask-image:url('{{ Vite::asset("resources/images/socials/{$exIcon}-solid.svg") }}');"></span>
                            @endforeach
                        </div>
                    </div>
                    <div :style="`font-size:${copyrightSize}`" class="border-t border-current/10 px-4 py-2.5 text-center opacity-60">
                        &copy; {{ now()->year }} {{ $brand }} &nbsp;|&nbsp; {{ __('Made with Wire-Up') }}
                    </div>
                </div>
                <div data-test="footer-variant" x-show="$wire.footer_layout === 'columns'" :style="{ background: footerBg, color: c.footer_text, fontFamily: bodyFont }">
                    <div class="grid grid-cols-3 gap-4 px-4 py-4 text-[10px]">
                        <div class="space-y-3">
                            <img x-cloak x-show="$wire.logo_footer?.preview" :src="logoSrc($wire.logo_footer)" alt="{{ $brand }}" class="h-4 w-auto object-contain" />
                            <div x-show="! $wire.logo_footer?.preview" class="font-semibold">{{ $brand }}</div>
                            <div class="opacity-60 text-[0.65rem] leading-relaxed">{{ __('Building something great.') }}</div>
                            <div class="flex items-center gap-2 opacity-70">
                                @foreach (['facebook', 'x-twitter', 'instagram'] as $exIcon)
                                    <span class="size-3 bg-current mask-center mask-no-repeat mask-contain" style="mask-image:url('{{ Vite::asset("resources/images/socials/{$exIcon}-solid.svg") }}'); -webkit-mask-image:url('{{ Vite::asset("resources/images/socials/{$exIcon}-solid.svg") }}');"></span>
                                @endforeach
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <div class="font-semibold border-b border-current/20 pb-1 mb-2">{{ __('Product') }}</div>
                            <div class="opacity-70">{{ __('Features') }}</div>
                            <div class="opacity-70">{{ __('Pricing') }}</div>
                            <div class="opacity-70">{{ __('Changelog') }}</div>
                        </div>
                        <div class="space-y-1.5">
                            <div class="font-semibold border-b border-current/20 pb-1 mb-2">{{ __('Company') }}</div>
                            <div class="opacity-70">{{ __('About') }}</div>
                            <div class="opacity-70">{{ __('Blog') }}</div>
                            <div class="opacity-70">{{ __('Contact') }}</div>
                        </div>
                    </div>
                    <div :style="`font-size:${copyrightSize}`" class="flex items-center justify-between border-t border-current/10 px-4 py-2.5 opacity-60">
                        <span>&copy; {{ now()->year }} {{ $brand }}, {{ __('All Rights Reserved') }}</span>
                        <span>{{ __('Made with Wire-Up') }}</span>
                    </div>
                </div>
                <div data-test="footer-variant" x-show="$wire.footer_layout === 'minimal'" class="px-4 py-3 text-center opacity-60" :style="{ background: footerBg, color: c.footer_text, fontFamily: bodyFont, fontSize: copyrightSize }">
                    &copy; {{ now()->year }} {{ $brand }} &nbsp;|&nbsp; {{ __('Made with Wire-Up') }}
                </div>
            </div>
        </div>

        <div class="order-1 md:col-span-3 space-y-8">
            <flux:select variant="listbox" wire:model="theme" label="{{ __('Theme Colors') }}" description="{{ __('Choose from pre-designed color schemes or create your own custom palette.') }}">
                @foreach ($presets as $key => $preset)
                    <flux:select.option value="{{ $key }}">
                        <span class="flex w-full items-center gap-3">
                            <span class="flex-1">{{ $preset['label'] }}</span>
                            <span class="flex items-center gap-1">
                                @foreach (['background', 'primary_bg', 'header_bg', 'text'] as $s)
                                    <span class="size-4 rounded-full ring-1 ring-black/30 dark:ring-white/30" style="background-color: {{ $preset['colors'][$s] }}"></span>
                                @endforeach
                            </span>
                        </span>
                    </flux:select.option>
                @endforeach
                <flux:select.option value="custom">
                    <span class="flex w-full items-center gap-3">
                        <span class="flex-1">{{ __('Custom') }}</span>
                        <span class="flex items-center gap-1">
                            @foreach (['background', 'primary_bg', 'header_bg', 'text'] as $s)
                                <span class="size-4 rounded-full ring-1 ring-black/30 dark:ring-white/30" style="background-color: {{ $colors[$s] ?? '#cccccc' }}"></span>
                            @endforeach
                        </span>
                    </span>
                </flux:select.option>
            </flux:select>

            <div x-cloak x-show="$wire.theme === 'custom'" class="space-y-6">
                @foreach ($slotsByGroup as $group => $groupSlots)
                    <div class="space-y-3">
                        <flux:heading size="sm">{{ __($group) }}</flux:heading>
                        <div class="grid sm:grid-cols-2 gap-4">
                            @foreach ($groupSlots as $slot => $def)
                                <flux:color-picker wire:model="colors.{{ $slot }}" label="{{ __($def['label']) }}" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <flux:select variant="listbox" wire:model="theme_dark" label="{{ __('Dark mode') }}" description="{{ __('Optional palette for visitors whose device prefers dark mode.') }}">
                <flux:select.option value="none">{{ __('Off') }}</flux:select.option>
                @foreach ($presets as $key => $preset)
                    <flux:select.option value="{{ $key }}">
                        <span class="flex w-full items-center gap-3">
                            <span class="flex-1">{{ $preset['label'] }}</span>
                            <span class="flex items-center gap-1">
                                @foreach (['background', 'primary_bg', 'header_bg', 'text'] as $s)
                                    <span class="size-4 rounded-full ring-1 ring-black/30 dark:ring-white/30" style="background-color: {{ $preset['colors'][$s] }}"></span>
                                @endforeach
                            </span>
                        </span>
                    </flux:select.option>
                @endforeach
                <flux:select.option value="custom">
                    <span class="flex w-full items-center gap-3">
                        <span class="flex-1">{{ __('Custom') }}</span>
                        <span class="flex items-center gap-1">
                            @foreach (['background', 'primary_bg', 'header_bg', 'text'] as $s)
                                <span class="size-4 rounded-full ring-1 ring-black/30 dark:ring-white/30" style="background-color: {{ $colors_dark[$s] ?? '#cccccc' }}"></span>
                            @endforeach
                        </span>
                    </span>
                </flux:select.option>
            </flux:select>

            <div x-cloak x-show="$wire.theme_dark === 'custom'" class="space-y-6">
                @foreach ($slotsByGroup as $group => $groupSlots)
                    <div class="space-y-3">
                        <flux:heading size="sm">{{ __($group) }} — {{ __('Dark mode') }}</flux:heading>
                        <div class="grid sm:grid-cols-2 gap-4">
                            @foreach ($groupSlots as $slot => $def)
                                <flux:color-picker wire:model="colors_dark.{{ $slot }}" label="{{ __($def['label']) }}" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-6">
                <flux:select variant="listbox" searchable wire:model="heading_font" label="{{ __('Heading font') }}">
                    @foreach ($fonts as $key => $font)
                        <flux:select.option value="{{ $key }}">
                            <span style="font-family: {{ $font['stack'] }}">{{ $font['label'] }}</span>
                        </flux:select.option>
                    @endforeach
                    <flux:select.option value="custom">{{ __('Custom') }}</flux:select.option>
                </flux:select>
                <div x-show="$wire.heading_font === 'custom'" x-cloak>
                    <flux:input wire:model="heading_font_custom" label="{{ __('Custom Google font') }}" placeholder="{{ __('Exact Google Fonts family name.') }}" />
                </div>

                <flux:select variant="listbox" searchable wire:model="body_font" label="{{ __('Body font') }}">
                    @foreach ($fonts as $key => $font)
                        <flux:select.option value="{{ $key }}">
                            <span style="font-family: {{ $font['stack'] }}">{{ $font['label'] }}</span>
                        </flux:select.option>
                    @endforeach
                    <flux:select.option value="custom">{{ __('Custom') }}</flux:select.option>
                </flux:select>
                <div x-show="$wire.body_font === 'custom'" x-cloak>
                    <flux:input wire:model="body_font_custom" label="{{ __('Custom Google font') }}" placeholder="{{ __('Exact Google Fonts family name.') }}" />
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-6">
                <flux:select variant="listbox" wire:model="heading_size" label="{{ __('Heading size') }}">
                    @foreach (array_keys(config('theme.heading_sizes')) as $key)
                        <flux:select.option value="{{ $key }}">{{ ucfirst($key) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model="body_size" label="{{ __('Body size') }}">
                    @foreach (array_keys(config('theme.body_sizes')) as $key)
                        <flux:select.option value="{{ $key }}">{{ ucfirst($key) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model="radius" label="{{ __('Corner radius') }}">
                    @foreach (array_keys(config('theme.radii')) as $key)
                        <flux:select.option value="{{ $key }}">{{ ucfirst($key) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model="border_width" label="{{ __('Border width') }}">
                    @foreach (array_keys(config('theme.border_widths')) as $key)
                        <flux:select.option value="{{ $key }}">{{ ucfirst($key) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model="container" label="{{ __('Content width') }}">
                    @foreach (array_keys(config('theme.containers')) as $key)
                        <flux:select.option value="{{ $key }}">{{ ucfirst($key) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model="block_spacing" label="{{ __('Block spacing') }}">
                    @foreach (config('theme.block_spacings') as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-6">
                <flux:heading size="sm">{{ __('Layout') }}</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Header layout') }}</flux:label>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach (config('theme.header_layouts') as $key => $layout)
                                <label class="group cursor-pointer">
                                    <input type="radio" wire:model="header_layout" value="{{ $key }}" class="sr-only peer" />
                                    <div class="overflow-hidden rounded-lg border-2 transition peer-checked:border-zinc-900 dark:peer-checked:border-zinc-100 border-zinc-200 dark:border-zinc-700 hover:border-zinc-400 dark:hover:border-zinc-500">
                                        @if ($key === 'simple')
                                            <svg viewBox="0 0 80 24" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="24" fill="#f4f4f5" class="dark:fill-zinc-800" />
                                                <rect x="6" y="9" width="14" height="6" rx="1" fill="#a1a1aa" />
                                                <rect x="46" y="10" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="57" y="10" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="68" y="9" width="6" height="6" rx="1" fill="#18181b" />
                                            </svg>
                                        @elseif ($key === 'centered')
                                            <svg viewBox="0 0 80 28" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="28" fill="#f4f4f5" class="dark:fill-zinc-800" />
                                                <rect x="30" y="5" width="20" height="6" rx="1" fill="#a1a1aa" />
                                                <rect x="14" y="16" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="25" y="16" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="36" y="16" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="47" y="15" width="8" height="6" rx="1" fill="#18181b" />
                                            </svg>
                                        @elseif ($key === 'split')
                                            <svg viewBox="0 0 80 24" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="24" fill="#f4f4f5" class="dark:fill-zinc-800" />
                                                <rect x="6" y="9" width="14" height="6" rx="1" fill="#a1a1aa" />
                                                <rect x="26" y="10" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="37" y="10" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="48" y="10" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="68" y="9" width="6" height="6" rx="1" fill="#18181b" />
                                            </svg>
                                        @else
                                            <svg viewBox="0 0 80 24" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="24" fill="#f4f4f5" class="dark:fill-zinc-800" />
                                                <rect x="6" y="9" width="14" height="6" rx="1" fill="#a1a1aa" />
                                                <rect x="66" y="10" width="4" height="2" rx="0.5" fill="#71717a" />
                                                <rect x="66" y="13.5" width="4" height="2" rx="0.5" fill="#71717a" />
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="mt-1.5 text-center text-xs text-zinc-600 dark:text-zinc-400 peer-checked:font-semibold peer-checked:text-zinc-900 dark:peer-checked:text-zinc-100">
                                        {{ $layout['label'] }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </flux:field>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <flux:switch wire:model="header_transparent" align="left" label="{{ __('Transparent background') }}" description="{{ __('Header sits over the page content.') }}" />
                        <flux:switch wire:model="header_sticky" align="left" label="{{ __('Sticky header') }}" description="{{ __('Header stays fixed at the top on scroll.') }}" />
                        <flux:switch wire:model="header_theme_toggle" align="left" label="{{ __('Light / dark toggle') }}" description="{{ __('Show a theme switch in the header. Needs a dark theme.') }}" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <flux:select variant="listbox" wire:model="header_logo_size" label="{{ __('Logo size') }}">
                            @foreach (config('theme.element_sizes') as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select variant="listbox" wire:model="header_nav_size" label="{{ __('Navigation size') }}">
                            @foreach (config('theme.element_sizes') as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select variant="listbox" wire:model="header_nav_hover" label="{{ __('Link hover effect') }}">
                            @foreach (config('theme.nav_hover_states') as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:separator variant="subtle" />

                    <flux:field>
                        <flux:label>{{ __('Footer layout') }}</flux:label>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach (config('theme.footer_layouts') as $key => $layout)
                                <label class="group cursor-pointer">
                                    <input type="radio" wire:model="footer_layout" value="{{ $key }}" class="sr-only peer" />
                                    <div class="overflow-hidden rounded-lg border-2 transition peer-checked:border-zinc-900 dark:peer-checked:border-zinc-100 border-zinc-200 dark:border-zinc-700 hover:border-zinc-400 dark:hover:border-zinc-500">
                                        @if ($key === 'simple')
                                            <svg viewBox="0 0 80 20" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="20" fill="#f4f4f5" class="dark:fill-zinc-800" />
                                                <rect x="6" y="7" width="14" height="6" rx="1" fill="#a1a1aa" />
                                                <rect x="54" y="8" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="65" y="8" width="8" height="4" rx="1" fill="#d4d4d8" />
                                            </svg>
                                        @elseif ($key === 'centered')
                                            <svg viewBox="0 0 80 28" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="28" fill="#f4f4f5" class="dark:fill-zinc-800" />
                                                <rect x="30" y="4" width="20" height="6" rx="1" fill="#a1a1aa" />
                                                <rect x="18" y="14" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="29" y="14" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="40" y="14" width="8" height="4" rx="1" fill="#d4d4d8" />
                                                <rect x="27" y="22" width="26" height="3" rx="1" fill="#d4d4d8" />
                                            </svg>
                                        @elseif ($key === 'columns')
                                            <svg viewBox="0 0 80 28" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="28" fill="#f4f4f5" class="dark:fill-zinc-800" />
                                                <rect x="6" y="6" width="16" height="5" rx="1" fill="#a1a1aa" />
                                                <rect x="6" y="14" width="18" height="3" rx="1" fill="#d4d4d8" />
                                                <rect x="6" y="19" width="14" height="3" rx="1" fill="#d4d4d8" />
                                                <rect x="34" y="6" width="12" height="3" rx="1" fill="#71717a" />
                                                <rect x="34" y="12" width="10" height="3" rx="1" fill="#d4d4d8" />
                                                <rect x="34" y="17" width="10" height="3" rx="1" fill="#d4d4d8" />
                                                <rect x="55" y="6" width="12" height="3" rx="1" fill="#71717a" />
                                                <rect x="55" y="12" width="10" height="3" rx="1" fill="#d4d4d8" />
                                                <rect x="55" y="17" width="10" height="3" rx="1" fill="#d4d4d8" />
                                            </svg>
                                        @else
                                            <svg viewBox="0 0 80 20" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                                <rect width="80" height="20" fill="#f4f4f5" class="dark:fill-zinc-800" />
                                                <rect x="25" y="8" width="30" height="5" rx="1" fill="#d4d4d8" />
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="mt-1.5 text-center text-xs text-zinc-600 dark:text-zinc-400 peer-checked:font-semibold peer-checked:text-zinc-900 dark:peer-checked:text-zinc-100">
                                        {{ $layout['label'] }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </flux:field>

                    <div>
                        <flux:switch wire:model="footer_transparent" align="left" label="{{ __('Transparent background') }}" description="{{ __('Footer sits over the page content.') }}" />
                    </div>
                </div>
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-6">
                <flux:heading size="sm">{{ __('Logos') }}</flux:heading>
                <flux:text class="!mt-1">{{ __('Add a dark-mode logo to swap it when the dark theme is active. Optional — the main logo is used when none is set.') }}</flux:text>
                <div class="space-y-6">
                    <livewire:admin.media-selector
                        wire:model="logo_header"
                        name="logo_header"
                        type="image"
                        :crops="['default' => ['label' => __('Header logo')]]"
                        label="{{ __('Header logo') }}"
                    />
                    <livewire:admin.media-selector
                        wire:model="logo_header_dark"
                        name="logo_header_dark"
                        type="image"
                        :crops="['default' => ['label' => __('Header logo (dark)')]]"
                        label="{{ __('Header logo — dark mode') }}"
                    />
                    <livewire:admin.media-selector
                        wire:model="logo_footer"
                        name="logo_footer"
                        type="image"
                        :crops="['default' => ['label' => __('Footer logo')]]"
                        label="{{ __('Footer logo') }}"
                    />
                    <livewire:admin.media-selector
                        wire:model="logo_footer_dark"
                        name="logo_footer_dark"
                        type="image"
                        :crops="['default' => ['label' => __('Footer logo (dark)')]]"
                        label="{{ __('Footer logo — dark mode') }}"
                    />
                </div>
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-6">
                <flux:heading size="sm">{{ __('Authentication') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Auth page layout') }}</flux:label>
                    <flux:description>{{ __('Applies to the sign-in, register and password pages.') }}</flux:description>
                    <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach (config('theme.auth_layouts') as $key => $layout)
                            <label class="group cursor-pointer">
                                <input type="radio" wire:model="auth_layout" value="{{ $key }}" class="sr-only peer" />
                                <div class="overflow-hidden rounded-lg border-2 transition peer-checked:border-zinc-900 dark:peer-checked:border-zinc-100 border-zinc-200 dark:border-zinc-700 hover:border-zinc-400 dark:hover:border-zinc-500">
                                    @if ($key === 'simple')
                                        <svg viewBox="0 0 80 56" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                            <rect width="80" height="56" fill="#f4f4f5" class="dark:fill-zinc-800" />
                                            <circle cx="40" cy="13" r="3" fill="#a1a1aa" />
                                            <rect x="28" y="23" width="24" height="5" rx="1" fill="#d4d4d8" />
                                            <rect x="28" y="32" width="24" height="5" rx="1" fill="#d4d4d8" />
                                            <rect x="28" y="41" width="24" height="5" rx="1" fill="#18181b" />
                                        </svg>
                                    @elseif ($key === 'card')
                                        <svg viewBox="0 0 80 56" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                            <rect width="80" height="56" fill="#e4e4e7" class="dark:fill-zinc-900" />
                                            <rect x="22" y="8" width="36" height="40" rx="3" fill="#ffffff" stroke="#d4d4d8" class="dark:fill-zinc-800" />
                                            <circle cx="40" cy="16" r="3" fill="#a1a1aa" />
                                            <rect x="28" y="24" width="24" height="4" rx="1" fill="#e4e4e7" />
                                            <rect x="28" y="31" width="24" height="4" rx="1" fill="#e4e4e7" />
                                            <rect x="28" y="38" width="24" height="4" rx="1" fill="#18181b" />
                                        </svg>
                                    @elseif ($key === 'split')
                                        <svg viewBox="0 0 80 56" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                            <rect width="80" height="56" fill="#ffffff" class="dark:fill-zinc-800" />
                                            <rect x="0" y="0" width="40" height="56" fill="#a1a1aa" />
                                            <path d="M4 42 L15 28 L24 42 Z" fill="#71717a" />
                                            <circle cx="30" cy="18" r="4" fill="#d4d4d8" />
                                            <rect x="48" y="22" width="26" height="5" rx="1" fill="#d4d4d8" />
                                            <rect x="48" y="31" width="26" height="5" rx="1" fill="#d4d4d8" />
                                            <rect x="48" y="40" width="26" height="5" rx="1" fill="#18181b" />
                                        </svg>
                                    @else
                                        <svg viewBox="0 0 80 56" class="w-full" xmlns="http://www.w3.org/2000/svg">
                                            <rect width="80" height="56" fill="#e4e4e7" class="dark:fill-zinc-900" />
                                            <rect x="8" y="12" width="64" height="32" rx="3" fill="#ffffff" stroke="#d4d4d8" class="dark:fill-zinc-800" />
                                            <rect x="11" y="15" width="26" height="26" rx="2" fill="#a1a1aa" />
                                            <rect x="42" y="19" width="26" height="4" rx="1" fill="#e4e4e7" />
                                            <rect x="42" y="26" width="26" height="4" rx="1" fill="#e4e4e7" />
                                            <rect x="42" y="33" width="26" height="4" rx="1" fill="#18181b" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="mt-1.5 text-center text-xs text-zinc-600 dark:text-zinc-400 peer-checked:font-semibold peer-checked:text-zinc-900 dark:peer-checked:text-zinc-100">
                                    {{ $layout['label'] }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                </flux:field>

                <div x-cloak x-show="['split', 'split-card'].includes($wire.auth_layout)" class="space-y-6">
                    <livewire:admin.media-selector
                        wire:model="auth_image"
                        name="auth_image"
                        type="image"
                        :crops="['default' => ['label' => __('Side image')]]"
                        label="{{ __('Side image') }}"
                    />

                    <flux:select variant="listbox" wire:model="auth_image_side" :label="__('Image position')">
                        <flux:select.option value="left">{{ __('Left') }}</flux:select.option>
                        <flux:select.option value="right">{{ __('Right') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Custom CSS') }}</flux:heading>
                <flux:text>{{ __('Add custom CSS rules that apply across the whole site.') }}</flux:text>
                <flux:modal.trigger name="site-custom-css">
                    <flux:button icon="code-bracket" variant="filled">{{ $custom_css !== '' ? __('Edit custom CSS') : __('Add custom CSS') }}</flux:button>
                </flux:modal.trigger>
            </div>

            <flux:modal name="site-custom-css" class="w-full md:max-w-2xl">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Custom CSS') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('These rules are added to every page on your site.') }}</flux:text>
                    </div>
                    <flux:textarea wire:model="custom_css" rows="12" class="font-mono text-sm" placeholder=".my-class &#123; color: red; &#125;" />
                    <div class="flex justify-end">
                        <flux:modal.close>
                            <flux:button variant="primary">{{ __('Done') }}</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Update') }}
                </flux:button>
            </div>
        </div>
    </form>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ __('Design') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Design') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
