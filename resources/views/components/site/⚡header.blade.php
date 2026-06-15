<?php

declare(strict_types=1);

use App\Services\SettingsService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public string $layout;

    /** @var array<int, array{label: string, url: string, target: string, appearance: string}> */
    public array $items = [];

    /** @var array<int, array{label: string, url: string, target: string, appearance: string}> */
    public array $links = [];

    /** @var array<int, array{label: string, url: string, target: string, appearance: string}> */
    public array $buttons = [];

    public string $brand;

    public ?string $logo = null;

    public bool $transparent;

    public string $position;

    public bool $showMobileMenu;

    public string $logoSize;

    public string $navSize;

    public string $navHover;

    public function mount(): void
    {
        $service = SettingsService::current();

        $layout = config('site.header_layout');
        $this->layout = is_string($layout) ? $layout : config()->string('theme.default_header_layout');
        $this->transparent = (bool) config('site.header_transparent', false);
        $sticky = (bool) config('site.header_sticky', false);

        $logoSize = config('site.header_logo_size');
        $this->logoSize = is_string($logoSize) ? $logoSize : config()->string('theme.default_header_logo_size');
        $navSize = config('site.header_nav_size');
        $this->navSize = is_string($navSize) ? $navSize : config()->string('theme.default_header_nav_size');
        $navHover = config('site.header_nav_hover');
        $this->navHover = is_string($navHover) ? $navHover : config()->string('theme.default_header_nav_hover');

        $this->items = $service->menu('header');
        $this->links = array_values(array_filter($this->items, fn (array $item): bool => $item['appearance'] === 'link'));
        $this->buttons = array_values(array_filter($this->items, fn (array $item): bool => $item['appearance'] === 'button'));

        $this->brand = $service->title() ?: config()->string('app.name');
        $this->logo = $service->logoUrl('logo_header');

        $this->position = $this->transparent ? 'absolute inset-x-0 top-0 z-40' : ($sticky ? 'sticky top-0 z-40 shadow-sm' : 'relative');

        $this->showMobileMenu = $this->items !== [] || count(resolve('localization')->getActiveLocales()) > 1;
    }

    /**
     * @return Collection<int, array{code: string, label: string, url: string, current: bool}>
     */
    #[Computed]
    public function languages(): Collection
    {
        $localization = resolve('localization');
        $current = $localization->getCurrentLocale();
        $url = url()->full();

        return collect($localization->getActiveLocales())
            ->map(fn (mixed $meta, string $code): array => [
                'code' => $code,
                'label' => (string) (data_get($meta, 'endonym') ?: data_get($meta, 'name') ?: $code),
                'url' => $localization->getLocalizedURL($url, $code),
                'current' => $code === $current,
            ])
            ->values();
    }
};
?>

<header
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    data-site-header
    data-layout="{{ $layout }}"
    @class([
        $position,
        'text-(--wire-header-text)',
        'bg-(--wire-header-bg)' => ! $transparent,
    ])
>
    @switch($layout)
        @case('centered')
            <div class="relative mx-auto max-w-7xl px-6 py-8 text-center space-y-6">
                <div class="absolute inset-e-6 top-4 max-md:hidden">
                    <x-site.language-picker :languages="$this->languages" />
                </div>
                <div class="flex justify-center">
                    <x-site.brand :logo="$logo" :brand="$brand" :size="$logoSize" />
                </div>
                <x-site.nav :items="$items" :size="$navSize" :hover="$navHover" class="mt-3 justify-center max-md:hidden" />
            </div>
            @break

        @case('split')
            <div class="mx-auto grid max-w-7xl grid-cols-3 items-center gap-6 px-6 py-4">
                <x-site.brand :logo="$logo" :brand="$brand" :size="$logoSize" />
                <x-site.nav :items="$links" :size="$navSize" :hover="$navHover" class="justify-center max-md:hidden" />
                <div class="flex items-center justify-end gap-4 max-md:hidden">
                    <x-site.nav :items="$buttons" :size="$navSize" :hover="$navHover" />
                    <x-site.language-picker :languages="$this->languages" />
                </div>
            </div>
            @break

        @case('minimal')
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <x-site.brand :logo="$logo" :brand="$brand" :size="$logoSize" />
            </div>
            @break

        @default
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
                <x-site.brand :logo="$logo" :brand="$brand" :size="$logoSize" />
                <div class="flex items-center gap-6 max-md:hidden">
                    <x-site.nav :items="$items" :size="$navSize" :hover="$navHover" />
                    <x-site.language-picker :languages="$this->languages" />
                </div>
            </div>
    @endswitch

    @if ($showMobileMenu)
        <button
            type="button"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open.toString()"
            aria-label="{{ __('Toggle menu') }}"
            @class([
                'absolute end-6 top-1/2 z-50 -translate-y-1/2 p-1 text-(--wire-header-text)',
                'md:hidden' => $layout !== 'minimal',
            ])
        >
            <flux:icon name="bars-3" x-show="! open" class="size-6" />
            <flux:icon name="x-mark" x-show="open" x-cloak class="size-6" />
        </button>

        <div @class(['md:hidden' => $layout !== 'minimal'])>
            <div
                x-show="open"
                x-cloak
                x-transition.opacity
                x-on:click="open = false"
                class="fixed inset-0 z-40 bg-black/40"
            ></div>

            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="fixed inset-y-0 right-0 z-40 flex w-72 max-w-[80vw] flex-col gap-6 bg-(--wire-header-bg) px-6 pb-6 pt-20 text-(--wire-header-text) shadow-xl"
            >
                <x-site.nav :items="$items" :size="$navSize" :hover="$navHover" class="flex-col items-start gap-4" />

                <x-site.language-picker :languages="$this->languages" align="start" />
            </div>
        </div>
    @endif
</header>
