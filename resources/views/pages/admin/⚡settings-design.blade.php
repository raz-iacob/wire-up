<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Settings;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

return new class extends Component
{
    public Settings $settings;

    public string $theme;

    public string $accent;

    public string $heading_font;

    public string $body_font;

    public string $heading_size;

    public string $body_size;

    public string $radius;

    public function mount(): void
    {
        $this->settings = Settings::current();
        $this->settings->load('translations');
        $meta = $this->settings->metadata ?? [];

        $this->theme = $meta['theme'] ?? config()->string('theme.default');
        $this->accent = $meta['accent'] ?? '#3b82f6';
        $this->heading_font = $meta['heading_font'] ?? config()->string('theme.default_font');
        $this->body_font = $meta['body_font'] ?? config()->string('theme.default_font');
        $this->heading_size = $meta['heading_size'] ?? config()->string('theme.default_heading_size');
        $this->body_size = $meta['body_size'] ?? config()->string('theme.default_body_size');
        $this->radius = $meta['radius'] ?? config()->string('theme.default_radius');
    }

    public function update(UpdateSettingsAction $action): void
    {
        $validated = $this->validate([
            'theme' => ['required', 'string', Rule::in([...array_keys(config()->array('theme.colors')), 'custom'])],
            'accent' => ['required_if:theme,custom', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'heading_font' => ['required', 'string', Rule::in(array_keys(config()->array('theme.fonts')))],
            'body_font' => ['required', 'string', Rule::in(array_keys(config()->array('theme.fonts')))],
            'heading_size' => ['required', 'string', Rule::in(array_keys(config()->array('theme.heading_sizes')))],
            'body_size' => ['required', 'string', Rule::in(array_keys(config()->array('theme.body_sizes')))],
            'radius' => ['required', 'string', Rule::in(array_keys(config()->array('theme.radii')))],
        ]);

        $action->handle($this->settings, ['metadata' => $validated]);

        Flux::toast(__('Design has been updated.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Design'))
            ->layout('layouts::admin');
    }
};
?>

@php
    $colors = config('theme.colors');
    $fonts = config('theme.fonts');
    $accentSwatches = collect($colors)->map(fn (array $c): string => $c['swatch'])->all();
    $fontStacks = collect($fonts)->map(fn (array $f): string => $f['stack'])->all();
    $brand = $settings->title ?: config('app.name');
@endphp

<x-settings-layout :subheading="__('Design the look of your public site — colours, fonts, and shape.')">
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="grid lg:grid-cols-5 gap-10 items-start"
        x-data="{
            accents: @js($accentSwatches),
            fonts: @js($fontStacks),
            headingSizes: @js(config('theme.heading_sizes')),
            bodySizes: @js(config('theme.body_sizes')),
            radii: @js(config('theme.radii')),
            get accentColor() { return $wire.theme === 'custom' ? $wire.accent : (this.accents[$wire.theme] || '#000000') },
            get headingFont() { return this.fonts[$wire.heading_font] || 'sans-serif' },
            get bodyFont() { return this.fonts[$wire.body_font] || 'sans-serif' },
            get headingSize() { return this.headingSizes[$wire.heading_size] || '1.5rem' },
            get bodySize() { return this.bodySizes[$wire.body_size] || '0.875rem' },
            get radius() { return this.radii[$wire.radius] || '0.5rem' },
        }">

        {{-- Live preview (right) --}}
        <div class="order-2 lg:col-span-2 lg:sticky lg:top-8">
            <flux:text class="mb-3">{{ __('Live preview') }}</flux:text>
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900"
                :style="`--accent:${accentColor}; --radius:${radius}`">

                {{-- header --}}
                <div class="flex items-center justify-between gap-4 border-b border-zinc-200 dark:border-zinc-700 px-4 py-3">
                    {{-- Phase 2: render the chosen logo here when one is set --}}
                    <span class="font-bold text-zinc-900 dark:text-white" :style="`font-family:${headingFont}`">{{ $brand }}</span>
                    <div class="flex items-center gap-3 text-xs text-zinc-600 dark:text-zinc-300" :style="`font-family:${bodyFont}`">
                        <span class="max-sm:hidden">{{ __('Home') }}</span>
                        <span class="max-sm:hidden">{{ __('About') }}</span>
                        <span class="px-2.5 py-1 font-medium text-white" style="background:var(--accent); border-radius:var(--radius)">{{ __('Sign up') }}</span>
                    </div>
                </div>

                {{-- hero --}}
                <div class="space-y-3 px-4 py-10 text-center">
                    <h1 class="font-bold text-zinc-900 dark:text-white" :style="`font-family:${headingFont}; font-size:${headingSize}`">{{ __('Build something great') }}</h1>
                    <p class="mx-auto max-w-xs text-zinc-500 dark:text-zinc-400" :style="`font-family:${bodyFont}; font-size:${bodySize}`">{{ __('A clean starting point for your next project, themed to your brand.') }}</p>
                    <div class="flex items-center justify-center gap-2 pt-2" :style="`font-family:${bodyFont}`">
                        <span class="px-3 py-1.5 text-sm font-medium text-white" style="background:var(--accent); border-radius:var(--radius)">{{ __('Get started') }}</span>
                        <span class="border border-zinc-300 px-3 py-1.5 text-sm text-zinc-700 dark:border-zinc-600 dark:text-zinc-200" style="border-radius:var(--radius)">{{ __('Learn more') }}</span>
                    </div>
                </div>

                {{-- content cards --}}
                <div class="grid grid-cols-3 gap-3 px-4 pb-8">
                    @for ($i = 0; $i < 3; $i++)
                        <div class="space-y-2">
                            <div class="h-10 bg-zinc-100 dark:bg-zinc-800" style="border-radius:var(--radius)"></div>
                            <div class="h-2 w-3/4 rounded bg-zinc-100 dark:bg-zinc-800"></div>
                            <div class="h-2 w-1/2 rounded bg-zinc-100 dark:bg-zinc-800"></div>
                        </div>
                    @endfor
                </div>

                {{-- footer --}}
                <div class="border-t border-zinc-200 px-4 py-3 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400" :style="`font-family:${bodyFont}`">
                    &copy; {{ $brand }}
                </div>
            </div>
        </div>

        {{-- controls (left) --}}
        <div class="order-1 lg:col-span-3 space-y-8">
            <flux:field>
                <flux:label>{{ __('Theme color') }}</flux:label>
                <flux:description>{{ __('Pick a colour scheme or choose Custom for your own.') }}</flux:description>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                    @foreach ($colors as $key => $color)
                        <button type="button" wire:click="$set('theme', '{{ $key }}')" title="{{ $color['label'] }}" aria-label="{{ $color['label'] }}"
                            class="size-7 cursor-pointer rounded-full ring-2 ring-offset-2 ring-offset-white transition dark:ring-offset-zinc-800"
                            style="background-color: {{ $color['swatch'] }}"
                            :class="$wire.theme === '{{ $key }}' ? 'ring-zinc-900 dark:ring-white' : 'ring-transparent'"></button>
                    @endforeach
                    <button type="button" wire:click="$set('theme', 'custom')" title="{{ __('Custom') }}" aria-label="{{ __('Custom') }}"
                        class="flex size-7 cursor-pointer items-center justify-center rounded-full ring-2 ring-offset-2 ring-offset-white transition dark:ring-offset-zinc-800 bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300"
                        :class="$wire.theme === 'custom' ? 'ring-zinc-900 dark:ring-white' : 'ring-transparent'">
                        <flux:icon icon="plus" variant="micro" />
                    </button>
                </div>
                <flux:error name="theme" />
            </flux:field>

            <div x-cloak x-show="$wire.theme === 'custom'">
                <flux:color-picker wire:model.live="accent" label="{{ __('Custom accent') }}" />
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

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Update') }}
                </flux:button>
            </div>
        </div>
    </form>
</x-settings-layout>
