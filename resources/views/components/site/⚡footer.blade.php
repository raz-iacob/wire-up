<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Services\SettingsService;
use Livewire\Component;

return new class extends Component
{
    public string $layout;

    /** @var array<int, array{label: string, url: string, target: string, appearance: string}> */
    public array $items = [];

    /** @var array<string, string> */
    public array $social = [];

    public string $brand;

    public string $tagline;

    public ?string $logo = null;

    public bool $transparent;

    public int $year;

    public function mount(): void
    {
        $settings = Settings::cached();
        $meta = is_array($settings?->metadata) ? $settings->metadata : [];
        $service = SettingsService::current();

        $this->layout = is_string($meta['footer_layout'] ?? null) ? $meta['footer_layout'] : config()->string('theme.default_footer_layout');
        $this->transparent = (bool) ($meta['footer_transparent'] ?? false);

        $this->items = $service->menu('footer');
        $this->social = $service->socialLinks();

        $this->brand = $settings && $settings->title !== '' ? $settings->title : config()->string('app.name');
        $this->tagline = $settings ? $settings->description : '';
        $this->logo = $settings?->hasImage('logo_footer') ? $settings->image('logo_footer', 'default', [], false) : null;

        $this->year = (int) now()->year;
    }
};
?>

<footer
    data-site-footer
    data-layout="{{ $layout }}"
    @class([
        'text-(--wire-footer-text)',
        'bg-(--wire-footer-bg)' => ! $transparent,
    ])
>
    @switch($layout)
        @case('centered')
            <div class="mx-auto max-w-3xl px-6 py-10 text-center">
                <div class="flex justify-center">
                    <x-site.brand :logo="$logo" :brand="$brand" />
                </div>
                <x-site.nav :items="$items" class="mt-4 justify-center" />
                <x-site.social :links="$social" class="mt-4 justify-center" />
                <div class="mt-6 border-t border-current/10 pt-4 text-sm opacity-70">
                    &copy; {{ $year }} {{ $brand }} &nbsp;|&nbsp; {{ __('Made with Wire-Up') }}
                </div>
            </div>
            @break

        @case('columns')
            <div class="mx-auto max-w-7xl px-6">
                <div class="grid gap-8 py-12 sm:grid-cols-2 md:grid-cols-3">
                    <div class="space-y-4">
                        <x-site.brand :logo="$logo" :brand="$brand" />
                        @if ($tagline !== '')
                            <p class="max-w-xs text-sm opacity-70">{{ $tagline }}</p>
                        @endif
                        <x-site.social :links="$social" />
                    </div>
                    @if ($items !== [])
                        <div class="md:col-span-2">
                            <x-site.nav :items="$items" class="flex-col items-start gap-3 md:flex-row md:flex-wrap md:gap-x-10" />
                        </div>
                    @endif
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-current/10 py-4 text-sm opacity-70">
                    <span>&copy; {{ $year }} {{ $brand }}. {{ __('All Rights Reserved') }}</span>
                    <span>{{ __('Made with Wire-Up') }}</span>
                </div>
            </div>
            @break

        @case('minimal')
            <div class="mx-auto max-w-7xl px-6 py-6 text-center text-sm opacity-70">
                &copy; {{ $year }} {{ $brand }} &nbsp;|&nbsp; {{ __('Made with Wire-Up') }}
            </div>
            @break

        @default
            <div class="mx-auto max-w-7xl px-6">
                <div class="flex flex-wrap items-center justify-between gap-6 py-10">
                    <x-site.brand :logo="$logo" :brand="$brand" />
                    <x-site.nav :items="$items" />
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-current/10 py-4 text-sm opacity-70">
                    <span>&copy; {{ $year }} {{ $brand }}. {{ __('All Rights Reserved') }}</span>
                    <div class="flex items-center gap-6">
                        <x-site.social :links="$social" />
                        <span>{{ __('Made with Wire-Up') }}</span>
                    </div>
                </div>
            </div>
    @endswitch
</footer>
