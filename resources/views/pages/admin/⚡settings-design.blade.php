<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Media;
use App\Models\Settings;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Component;

return new class extends Component
{
    public Settings $settings;

    public string $theme;

    /**
     * @var array<string, string>
     */
    public array $colors = [];

    public string $heading_font;

    public string $body_font;

    public string $heading_size;

    public string $body_size;

    public string $radius;

    public string $header_layout;

    public bool $header_transparent = false;

    public bool $header_sticky = false;

    public string $footer_layout;

    public bool $footer_transparent = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $logo_header = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $logo_footer = null;

    public function mount(): void
    {
        $this->settings = Settings::current();
        $this->settings->load('translations', 'media');
        $meta = $this->settings->metadata ?? [];

        $this->theme = is_string($meta['theme'] ?? null) ? $meta['theme'] : config()->string('theme.default');
        $this->colors = $this->resolvePalette($this->theme, $meta);
        $this->heading_font = $meta['heading_font'] ?? config()->string('theme.default_font');
        $this->body_font = $meta['body_font'] ?? config()->string('theme.default_font');
        $this->heading_size = $meta['heading_size'] ?? config()->string('theme.default_heading_size');
        $this->body_size = $meta['body_size'] ?? config()->string('theme.default_body_size');
        $this->radius = $meta['radius'] ?? config()->string('theme.default_radius');
        $this->header_layout = is_string($meta['header_layout'] ?? null) ? $meta['header_layout'] : config()->string('theme.default_header_layout');
        $this->header_transparent = (bool) ($meta['header_transparent'] ?? false);
        $this->header_sticky = (bool) ($meta['header_sticky'] ?? false);
        $this->footer_layout = is_string($meta['footer_layout'] ?? null) ? $meta['footer_layout'] : config()->string('theme.default_footer_layout');
        $this->footer_transparent = (bool) ($meta['footer_transparent'] ?? false);

        $this->logo_header = $this->mediaForRole('logo_header');
        $this->logo_footer = $this->mediaForRole('logo_footer');
    }

    public function updatedTheme(string $value): void
    {
        if ($value !== 'custom') {
            $this->colors = $this->presetColors($value);
        }
    }

    public function update(UpdateSettingsAction $action): void
    {
        $rules = [
            'theme' => ['required', 'string', Rule::in([...array_keys(config()->array('theme.presets')), 'custom'])],
            'heading_font' => ['required', 'string', Rule::in(array_keys(config()->array('theme.fonts')))],
            'body_font' => ['required', 'string', Rule::in(array_keys(config()->array('theme.fonts')))],
            'heading_size' => ['required', 'string', Rule::in(array_keys(config()->array('theme.heading_sizes')))],
            'body_size' => ['required', 'string', Rule::in(array_keys(config()->array('theme.body_sizes')))],
            'radius' => ['required', 'string', Rule::in(array_keys(config()->array('theme.radii')))],
            'header_layout' => ['required', 'string', Rule::in(array_keys(config()->array('theme.header_layouts')))],
            'header_transparent' => ['boolean'],
            'header_sticky' => ['boolean'],
            'footer_layout' => ['required', 'string', Rule::in(array_keys(config()->array('theme.footer_layouts')))],
            'footer_transparent' => ['boolean'],
            'logo_header' => ['nullable', 'array'],
            'logo_header.id' => ['nullable', 'integer', 'exists:media,id'],
            'logo_footer' => ['nullable', 'array'],
            'logo_footer.id' => ['nullable', 'integer', 'exists:media,id'],
        ];

        foreach (array_keys(config()->array('theme.slots')) as $slot) {
            $rules["colors.$slot"] = ['required_if:theme,custom', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        $validated = $this->validate($rules);

        $metadata = Arr::only($validated, ['theme', 'heading_font', 'body_font', 'heading_size', 'body_size', 'radius', 'header_layout', 'header_transparent', 'header_sticky', 'footer_layout', 'footer_transparent']);

        if ($this->theme === 'custom') {
            $metadata['colors'] = Arr::only($this->colors, array_keys(config()->array('theme.slots')));
        }

        $action->handle($this->settings, [
            'metadata' => $metadata,
            'logo_header' => $this->logo_header,
            'logo_footer' => $this->logo_footer,
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
        if ($theme === 'custom' && is_array($meta['colors'] ?? null)) {
            return $this->onlyStringColors($meta['colors']);
        }

        return $this->presetColors($theme);
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

    /**
     * @return array<string, mixed>|null
     */
    private function mediaForRole(string $role): ?array
    {
        $media = $this->settings->media
            ->first(fn (Media $media): bool => $media->pivot->role === $role);

        return $media ? $this->mediaToItem($media) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaToItem(Media $media): array
    {
        return [
            'id' => $media->id,
            'source' => $media->source,
            'preview' => $media->preview,
            'crop_src' => $media->cropSrc,
            'filename' => $media->filename,
            'alt_text' => $media->alt_text,
            'mime_type' => $media->mime_type,
            'thumbnail' => $media->thumbnail,
            'icon' => $media->type->icon(),
            'size' => $media->size,
            'duration' => $media->duration,
            'width' => $media->width,
            'height' => $media->height,
            'dimensions' => $media->dimensions,
            'created_at' => $media->created_at->toDateTimeString(),
            'crop' => $media->pivot->crop ?? [],
            'metadata' => $media->pivot->metadata ?? [],
        ];
    }
};
?>

@php
    $presets = config('theme.presets');
    $fonts = config('theme.fonts');
    $fontStacks = collect($fonts)->map(fn (array $f): string => $f['stack'])->all();
    $brand = $settings->title ?: config('app.name');

    $slotsByGroup = [];
    foreach (config('theme.slots') as $slot => $def) {
        $slotsByGroup[$def['group']][$slot] = $def;
    }

    $googleFamilies = collect($fonts)->pluck('google')->filter()->unique()->values();
    $previewFontsUrl = $googleFamilies->isEmpty() ? null : 'https://fonts.googleapis.com/css2?'
        .$googleFamilies->map(fn (string $f): string => 'family='.str_replace(' ', '+', $f).':wght@400;500;600;700')->implode('&')
        .'&display=swap';
@endphp

<x-settings-layout :subheading="__('Design the look of your public site — colours, fonts, and shape.')">
    @if ($previewFontsUrl)
        <link id="design-preview-fonts" rel="stylesheet" href="{{ $previewFontsUrl }}" />
    @endif
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="grid lg:grid-cols-5 gap-10 items-start"
        x-data="{
            fonts: @js($fontStacks),
            headingSizes: @js(config('theme.heading_sizes')),
            bodySizes: @js(config('theme.body_sizes')),
            radii: @js(config('theme.radii')),
            presetPalettes: @js(collect($presets)->map(fn (array $p): array => $p['colors'])),
            get c() {
                return ($wire.theme !== 'custom' && this.presetPalettes[$wire.theme])
                    ? this.presetPalettes[$wire.theme]
                    : ($wire.colors || {})
            },
            get headingFont() { return this.fonts[$wire.heading_font] || 'sans-serif' },
            get bodyFont() { return this.fonts[$wire.body_font] || 'sans-serif' },
            get headingSize() { return this.headingSizes[$wire.heading_size] || '1.5rem' },
            get bodySize() { return this.bodySizes[$wire.body_size] || '0.875rem' },
            get radius() { return this.radii[$wire.radius] || '0.5rem' },
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
        }">

        {{-- Live preview (right) --}}
        <div class="order-2 lg:col-span-2 lg:sticky lg:top-8">
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">

                {{-- header wrapper — sticky shadow + badge --}}
                <div class="relative transition-shadow" :class="$wire.header_sticky ? 'shadow-md' : ''">
                    {{-- simple: logo left, nav right --}}
                    <div data-test="header-variant" x-show="$wire.header_layout === 'simple'" class="flex items-center justify-between gap-4 px-4 py-3" :style="{ background: headerBg, color: c.header_text, fontFamily: headingFont }">
                        <span class="flex items-center gap-2">
                            <img x-cloak x-show="$wire.logo_header?.preview" :src="logoSrc($wire.logo_header)" alt="{{ $brand }}" class="h-7 w-auto object-contain" />
                            <span x-show="! $wire.logo_header?.preview" class="font-bold text-sm">{{ $brand }}</span>
                        </span>
                        <div class="flex items-center gap-3 text-xs" :style="`font-family:${bodyFont}`">
                            <span>{{ __('Home') }}</span>
                            <span>{{ __('About') }}</span>
                            <span class="px-2.5 py-1 font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border-radius:${radius}`">{{ __('Sign up') }}</span>
                        </div>
                    </div>
                    {{-- centered: logo centered, nav below --}}
                    <div data-test="header-variant" x-show="$wire.header_layout === 'centered'" class="px-4 py-3 text-center" :style="{ background: headerBg, color: c.header_text, fontFamily: headingFont }">
                        <div class="flex justify-center">
                            <img x-cloak x-show="$wire.logo_header?.preview" :src="logoSrc($wire.logo_header)" alt="{{ $brand }}" class="h-7 w-auto object-contain" />
                            <span x-show="! $wire.logo_header?.preview" class="font-bold text-sm">{{ $brand }}</span>
                        </div>
                        <div class="flex items-center justify-center gap-4 mt-2 text-xs" :style="`font-family:${bodyFont}`">
                            <span>{{ __('Home') }}</span>
                            <span>{{ __('About') }}</span>
                            <span>{{ __('Pricing') }}</span>
                            <span class="px-2.5 py-1 font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border-radius:${radius}`">{{ __('Sign up') }}</span>
                        </div>
                    </div>
                    {{-- split: logo left, nav center, CTA right --}}
                    <div data-test="header-variant" x-show="$wire.header_layout === 'split'" class="grid grid-cols-3 items-center gap-2 px-4 py-3" :style="{ background: headerBg, color: c.header_text, fontFamily: headingFont }">
                        <span class="flex items-center gap-2">
                            <img x-cloak x-show="$wire.logo_header?.preview" :src="logoSrc($wire.logo_header)" alt="{{ $brand }}" class="h-7 w-auto object-contain" />
                            <span x-show="! $wire.logo_header?.preview" class="font-bold text-sm">{{ $brand }}</span>
                        </span>
                        <div class="flex items-center justify-center gap-4 text-xs" :style="`font-family:${bodyFont}`">
                            <span>{{ __('Home') }}</span>
                            <span>{{ __('About') }}</span>
                            <span>{{ __('Pricing') }}</span>
                        </div>
                        <div class="flex justify-end">
                            <span class="px-2.5 py-1 text-xs font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border-radius:${radius}`">{{ __('Sign up') }}</span>
                        </div>
                    </div>
                    {{-- minimal: logo only --}}
                    <div data-test="header-variant" x-show="$wire.header_layout === 'minimal'" class="flex items-center justify-between px-4 py-3" :style="{ background: headerBg, color: c.header_text, fontFamily: headingFont }">
                        <span class="flex items-center gap-2">
                            <img x-cloak x-show="$wire.logo_header?.preview" :src="logoSrc($wire.logo_header)" alt="{{ $brand }}" class="h-7 w-auto object-contain" />
                            <span x-show="! $wire.logo_header?.preview" class="font-bold text-sm">{{ $brand }}</span>
                        </span>
                        <div class="flex items-center gap-1.5">
                            <span class="h-0.5 w-4 rounded-full" :style="`background:${c.header_text}`"></span>
                            <span class="h-0.5 w-3 rounded-full" :style="`background:${c.header_text}`"></span>
                            <span class="h-0.5 w-2 rounded-full" :style="`background:${c.header_text}`"></span>
                        </div>
                    </div>
                    {{-- sticky badge --}}
                    <div x-cloak x-show="$wire.header_sticky" class="absolute top-1 right-1 rounded px-1.5 py-0.5 text-[0.55rem] font-medium bg-zinc-800/70 text-white">{{ __('Sticky') }}</div>
                </div>

                {{-- body --}}
                <div data-test="preview-body" class="px-4 py-10" :style="`background:${c.background}; color:${c.text}`">
                    <div class="space-y-3 text-center">
                        <h1 class="font-bold" :style="`font-family:${headingFont}; font-size:${headingSize}`">{{ __('Build something great') }}</h1>
                        <p class="mx-auto max-w-xs" :style="`color:${c.muted}; font-family:${bodyFont}; font-size:${bodySize}`">{{ __('A clean starting point for your next project, themed to your brand.') }}</p>
                        <div class="flex items-center justify-center gap-2 pt-2" :style="`font-family:${bodyFont}`">
                            <span class="px-3 py-1.5 text-sm font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border-radius:${radius}`">{{ __('Get started') }}</span>
                            <span class="px-3 py-1.5 text-sm font-medium" :style="`background:${c.secondary_bg}; color:${c.secondary_text}; border-radius:${radius}`">{{ __('Learn more') }}</span>
                        </div>
                        <div class="mx-auto flex max-w-xs items-center gap-2 pt-2" :style="`font-family:${bodyFont}`">
                            <span class="flex-1 px-3 py-1.5 text-left text-sm" :style="`background:${c.input_bg}; color:${c.input_text}; border:1px solid ${c.input_border}; border-radius:${radius}`">name@email.com</span>
                            <span class="px-3 py-1.5 text-sm font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border-radius:${radius}`">{{ __('Subscribe') }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3 pt-6">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="space-y-1.5 p-3 text-left" :style="`background:${c.card_bg}; border:1px solid ${c.card_border}; border-radius:${radius}`">
                                <div class="text-[0.65rem] font-semibold" :style="`color:${c.card_text}; font-family:${headingFont}`">{{ __('Card title') }}</div>
                                <div class="text-[0.6rem] leading-snug" :style="`color:${c.muted}; font-family:${bodyFont}`">{{ __('A short supporting line of text.') }}</div>
                            </div>
                        @endfor
                    </div>
                </div>

                {{-- footer --}}
                {{-- simple: logo+nav top, copyright bar bottom --}}
                <div data-test="footer-variant" x-show="$wire.footer_layout === 'simple'" :style="{ background: footerBg, color: c.footer_text, fontFamily: bodyFont }">
                    <div class="flex items-center justify-between gap-4 px-4 py-5 text-xs">
                        <span class="flex items-center gap-2">
                            <img x-cloak x-show="$wire.logo_footer?.preview" :src="logoSrc($wire.logo_footer)" alt="{{ $brand }}" class="h-7 w-auto object-contain" />
                            <span x-show="! $wire.logo_footer?.preview" class="text-sm font-semibold">{{ $brand }}</span>
                        </span>
                        <div class="flex items-center gap-4 opacity-80">
                            <span>{{ __('Services') }}</span>
                            <span>{{ __('About') }}</span>
                            <span>{{ __('Contact') }}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-current/10 px-4 py-2.5 text-[0.6rem] opacity-60">
                        <span>&copy; {{ now()->year }} {{ $brand }}, {{ __('All Rights Reserved') }}</span>
                        <span>{{ __('Made with Wire-Up') }}</span>
                    </div>
                </div>
                {{-- centered: logo+nav centered, copyright+badge inline --}}
                <div data-test="footer-variant" x-show="$wire.footer_layout === 'centered'" :style="{ background: footerBg, color: c.footer_text, fontFamily: bodyFont }">
                    <div class="px-4 py-5 text-center text-xs space-y-3">
                        <div class="flex justify-center">
                            <img x-cloak x-show="$wire.logo_footer?.preview" :src="logoSrc($wire.logo_footer)" alt="{{ $brand }}" class="h-6 w-auto object-contain" />
                            <span x-show="! $wire.logo_footer?.preview" class="font-semibold">{{ $brand }}</span>
                        </div>
                        <div class="flex items-center justify-center gap-4 opacity-80">
                            <span>{{ __('Privacy') }}</span>
                            <span>{{ __('Terms') }}</span>
                            <span>{{ __('Contact') }}</span>
                        </div>
                    </div>
                    <div class="border-t border-current/10 px-4 py-2.5 text-center text-[0.6rem] opacity-60">
                        &copy; {{ now()->year }} {{ $brand }} &nbsp;|&nbsp; {{ __('Made with Wire-Up') }}
                    </div>
                </div>
                {{-- columns: logo+tagline left, link columns right, bar at bottom --}}
                <div data-test="footer-variant" x-show="$wire.footer_layout === 'columns'" :style="{ background: footerBg, color: c.footer_text, fontFamily: bodyFont }">
                    <div class="grid grid-cols-3 gap-4 px-4 py-5 text-xs">
                        <div class="space-y-3">
                            <img x-cloak x-show="$wire.logo_footer?.preview" :src="logoSrc($wire.logo_footer)" alt="{{ $brand }}" class="h-7 w-auto object-contain" />
                            <div x-show="! $wire.logo_footer?.preview" class="font-semibold">{{ $brand }}</div>
                            <div class="opacity-60 text-[0.65rem] leading-relaxed">{{ __('Building something great.') }}</div>
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
                    <div class="flex items-center justify-between border-t border-current/10 px-4 py-2.5 text-[0.6rem] opacity-60">
                        <span>&copy; {{ now()->year }} {{ $brand }}, {{ __('All Rights Reserved') }}</span>
                        <span>{{ __('Made with Wire-Up') }}</span>
                    </div>
                </div>
                {{-- minimal: copyright + badge inline, centered --}}
                <div data-test="footer-variant" x-show="$wire.footer_layout === 'minimal'" class="px-4 py-3 text-center text-[0.6rem] opacity-60" :style="{ background: footerBg, color: c.footer_text, fontFamily: bodyFont }">
                    &copy; {{ now()->year }} {{ $brand }} &nbsp;|&nbsp; {{ __('Made with Wire-Up') }}
                </div>
            </div>
        </div>

        {{-- controls (left) --}}
        <div class="order-1 lg:col-span-3 space-y-8">
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

            <flux:separator variant="subtle" />

            <div class="grid sm:grid-cols-2 gap-6">
                <flux:select variant="listbox" wire:model="heading_font" label="{{ __('Heading font') }}">
                    @foreach ($fonts as $key => $font)
                        <flux:select.option value="{{ $key }}">
                            <span style="font-family: {{ $font['stack'] }}">{{ $font['label'] }}</span>
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model="body_font" label="{{ __('Body font') }}">
                    @foreach ($fonts as $key => $font)
                        <flux:select.option value="{{ $key }}">
                            <span style="font-family: {{ $font['stack'] }}">{{ $font['label'] }}</span>
                        </flux:select.option>
                    @endforeach
                </flux:select>
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
                        <flux:switch wire:model="header_transparent" label="{{ __('Transparent background') }}" description="{{ __('Header sits over the page content.') }}" />
                        <flux:switch wire:model="header_sticky" label="{{ __('Sticky header') }}" description="{{ __('Header stays fixed at the top on scroll.') }}" />
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
                        <flux:switch wire:model="footer_transparent" label="{{ __('Transparent background') }}" description="{{ __('Footer sits over the page content.') }}" />
                    </div>
                </div>
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-6">
                <flux:heading size="sm">{{ __('Logos') }}</flux:heading>
                <livewire:media-selector
                    wire:model="logo_header"
                    name="logo_header"
                    type="image"
                    :crops="['default' => ['label' => __('Header logo'), 'w' => 480, 'h' => 160]]"
                    label="{{ __('Header logo') }}"
                />
                <livewire:media-selector
                    wire:model="logo_footer"
                    name="logo_footer"
                    type="image"
                    :crops="['default' => ['label' => __('Footer logo'), 'w' => 480, 'h' => 160]]"
                    label="{{ __('Footer logo') }}"
                />
            </div>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Update') }}
                </flux:button>
            </div>
        </div>
    </form>
</x-settings-layout>
