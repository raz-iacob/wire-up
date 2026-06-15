<?php

declare(strict_types=1);

use App\Services\SettingsService;
use Livewire\Component;

return new class extends Component
{
    public string $layout;

    /** @var array<int, array{label: string, url: string, target: string, appearance: string}> */
    public array $items = [];

    /** @var array<string, string> */
    public array $social = [];

    public string $socialVariant;

    public string $brand;

    public string $tagline;

    public ?string $logo = null;

    public bool $transparent;

    public int $year;

    public function mount(): void
    {
        $service = SettingsService::current();

        $layout = config('site.footer_layout');
        $this->layout = is_string($layout) ? $layout : config()->string('theme.default_footer_layout');
        $this->transparent = (bool) config('site.footer_transparent', false);

        $this->items = $service->menu('footer');
        $this->social = $service->socialLinks();
        $this->socialVariant = $service->socialIconVariant();

        $this->brand = $service->title() ?: config()->string('app.name');
        $this->tagline = $service->description();
        $this->logo = $service->logoUrl('logo_footer');

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
                <x-site.social :links="$social" :variant="$socialVariant" class="mt-4 justify-center" />
                <div class="mt-6 border-t border-current/10 pt-4 text-[length:calc(var(--wire-body-size)*0.75)] opacity-70">
                    &copy; {{ $year }} {{ $brand }} &nbsp;|&nbsp; <a href="https://wire-up.dev" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ __('Made with Wire-Up') }}</a>
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
                        <x-site.social :links="$social" :variant="$socialVariant" />
                    </div>
                    @if ($items !== [])
                        <div class="md:col-span-2">
                            <x-site.nav :items="$items" class="flex-col items-start gap-3 md:flex-row md:flex-wrap md:gap-x-10" />
                        </div>
                    @endif
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-current/10 py-4 text-[length:calc(var(--wire-body-size)*0.75)] opacity-70">
                    <span>&copy; {{ $year }} {{ $brand }}. {{ __('All Rights Reserved') }}</span>
                    <span><a href="https://wire-up.dev" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ __('Made with Wire-Up') }}</a></span>
                </div>
            </div>
            @break

        @case('minimal')
            <div class="mx-auto max-w-7xl px-6 py-6 text-center text-[length:calc(var(--wire-body-size)*0.75)] opacity-70">
                &copy; {{ $year }} {{ $brand }} &nbsp;|&nbsp; <a href="https://wire-up.dev" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ __('Made with Wire-Up') }}</a>
            </div>
            @break

        @default
            <div class="mx-auto max-w-7xl px-6">
                <div class="flex flex-wrap items-start justify-between gap-6 py-10">
                    <div class="space-y-4">
                        <x-site.brand :logo="$logo" :brand="$brand" />
                        <x-site.social :links="$social" :variant="$socialVariant" />
                    </div>
                    <x-site.nav :items="$items" />
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-current/10 py-4 text-[length:calc(var(--wire-body-size)*0.75)] opacity-70">
                    <span>&copy; {{ $year }} {{ $brand }}. {{ __('All Rights Reserved') }}</span>
                    <span><a href="https://wire-up.dev" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ __('Made with Wire-Up') }}</a></span>
                </div>
            </div>
    @endswitch
</footer>
