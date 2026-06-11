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
            'logo_header' => ['nullable', 'array'],
            'logo_header.id' => ['nullable', 'integer', 'exists:media,id'],
            'logo_footer' => ['nullable', 'array'],
            'logo_footer.id' => ['nullable', 'integer', 'exists:media,id'],
        ];

        foreach (array_keys(config()->array('theme.slots')) as $slot) {
            $rules["colors.$slot"] = ['required_if:theme,custom', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        $validated = $this->validate($rules);

        $metadata = Arr::only($validated, ['theme', 'heading_font', 'body_font', 'heading_size', 'body_size', 'radius']);

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
@endphp

<x-settings-layout :subheading="__('Design the look of your public site — colours, fonts, and shape.')">
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="grid lg:grid-cols-5 gap-10 items-start"
        x-data="{
            fonts: @js($fontStacks),
            headingSizes: @js(config('theme.heading_sizes')),
            bodySizes: @js(config('theme.body_sizes')),
            radii: @js(config('theme.radii')),
            get c() { return $wire.colors || {} },
            get headingFont() { return this.fonts[$wire.heading_font] || 'sans-serif' },
            get bodyFont() { return this.fonts[$wire.body_font] || 'sans-serif' },
            get headingSize() { return this.headingSizes[$wire.heading_size] || '1.5rem' },
            get bodySize() { return this.bodySizes[$wire.body_size] || '0.875rem' },
            get radius() { return this.radii[$wire.radius] || '0.5rem' },
        }">

        {{-- Live preview (right) --}}
        <div class="order-2 lg:col-span-2 lg:sticky lg:top-8">
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">

                {{-- header --}}
                <div class="flex items-center justify-between gap-4 px-4 py-3" :style="`background:${c.header_bg}; color:${c.header_text}; font-family:${headingFont}`">
                    <img x-cloak x-show="$wire.logo_header?.preview" :src="$wire.logo_header?.preview" alt="{{ $brand }}" class="h-7 w-auto object-contain" />
                    <span x-show="! $wire.logo_header?.preview" class="font-bold">{{ $brand }}</span>
                    <div class="flex items-center gap-3 text-xs" :style="`font-family:${bodyFont}`">
                        <span class="max-sm:hidden">{{ __('Home') }}</span>
                        <span class="max-sm:hidden">{{ __('About') }}</span>
                        <span class="px-2.5 py-1 font-medium" :style="`background:${c.primary_bg}; color:${c.primary_text}; border-radius:${radius}`">{{ __('Sign up') }}</span>
                    </div>
                </div>

                {{-- body --}}
                <div class="px-4 py-10" :style="`background:${c.background}; color:${c.text}`">
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
                <div class="flex items-center justify-between gap-4 px-4 py-4 text-xs" :style="`background:${c.footer_bg}; color:${c.footer_text}; font-family:${bodyFont}`">
                    <img x-cloak x-show="$wire.logo_footer?.preview" :src="$wire.logo_footer?.preview" alt="{{ $brand }}" class="h-6 w-auto object-contain" />
                    <span x-show="! $wire.logo_footer?.preview">&copy; {{ $brand }}</span>
                    <span>{{ __('Privacy') }} · {{ __('Terms') }}</span>
                </div>
            </div>
        </div>

        {{-- controls (left) --}}
        <div class="order-1 lg:col-span-3 space-y-8">
            <flux:select variant="listbox" wire:model.live="theme" label="{{ __('Theme Colors') }}" description="{{ __('Choose from pre-designed color schemes or create your own custom palette.') }}">
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
                                <flux:color-picker wire:model.live="colors.{{ $slot }}" label="{{ __($def['label']) }}" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <flux:separator variant="subtle" />

            <div class="grid sm:grid-cols-2 gap-6">
                <flux:select variant="listbox" wire:model.live="heading_font" label="{{ __('Heading font') }}">
                    @foreach ($fonts as $key => $font)
                        <flux:select.option value="{{ $key }}">{{ $font['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model.live="body_font" label="{{ __('Body font') }}">
                    @foreach ($fonts as $key => $font)
                        <flux:select.option value="{{ $key }}">{{ $font['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model.live="heading_size" label="{{ __('Heading size') }}">
                    @foreach (array_keys(config('theme.heading_sizes')) as $key)
                        <flux:select.option value="{{ $key }}">{{ ucfirst($key) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model.live="body_size" label="{{ __('Body size') }}">
                    @foreach (array_keys(config('theme.body_sizes')) as $key)
                        <flux:select.option value="{{ $key }}">{{ ucfirst($key) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select variant="listbox" wire:model.live="radius" label="{{ __('Corner radius') }}">
                    @foreach (array_keys(config('theme.radii')) as $key)
                        <flux:select.option value="{{ $key }}">{{ ucfirst($key) }}</flux:select.option>
                    @endforeach
                </flux:select>
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
