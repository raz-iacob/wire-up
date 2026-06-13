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

    /** @var array<int, array{label: string, url: string, target: string, appearance: string}> */
    public array $links = [];

    /** @var array<int, array{label: string, url: string, target: string, appearance: string}> */
    public array $buttons = [];

    public string $brand;

    public ?string $logo = null;

    public bool $transparent;

    public string $position;

    public function mount(): void
    {
        $settings = Settings::cached();
        $meta = is_array($settings?->metadata) ? $settings->metadata : [];

        $this->layout = is_string($meta['header_layout'] ?? null) ? $meta['header_layout'] : config()->string('theme.default_header_layout');
        $this->transparent = (bool) ($meta['header_transparent'] ?? false);
        $sticky = (bool) ($meta['header_sticky'] ?? false);

        $this->items = SettingsService::current()->menu('header');
        $this->links = array_values(array_filter($this->items, fn (array $item): bool => $item['appearance'] === 'link'));
        $this->buttons = array_values(array_filter($this->items, fn (array $item): bool => $item['appearance'] === 'button'));

        $this->brand = $settings && $settings->title !== '' ? $settings->title : config()->string('app.name');
        $this->logo = $settings?->hasImage('logo_header') ? $settings->image('logo_header', 'default', [], false) : null;

        $this->position = $this->transparent ? 'absolute inset-x-0 top-0 z-40' : ($sticky ? 'sticky top-0 z-40 shadow-sm' : 'relative');
    }
};
?>

<header
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
            <div class="mx-auto max-w-7xl px-6 py-4 text-center">
                <div class="flex justify-center">
                    <x-site.brand :logo="$logo" :brand="$brand" />
                </div>
                <x-site.nav :items="$items" class="mt-3 justify-center" />
            </div>
            @break

        @case('split')
            <div class="mx-auto grid max-w-7xl grid-cols-3 items-center gap-6 px-6 py-4">
                <x-site.brand :logo="$logo" :brand="$brand" />
                <x-site.nav :items="$links" class="justify-center" />
                <x-site.nav :items="$buttons" class="justify-end" />
            </div>
            @break

        @case('minimal')
            <div x-data="{ open: false }" class="relative mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <x-site.brand :logo="$logo" :brand="$brand" />
                @if ($items !== [])
                    <button
                        type="button"
                        @click="open = ! open"
                        class="inline-flex flex-col gap-1.5 p-1"
                        aria-label="{{ __('Toggle menu') }}"
                        aria-expanded="false"
                        x-bind:aria-expanded="open.toString()"
                    >
                        <span class="h-0.5 w-6 rounded-full bg-current"></span>
                        <span class="h-0.5 w-5 rounded-full bg-current"></span>
                        <span class="h-0.5 w-4 rounded-full bg-current"></span>
                    </button>
                    <div
                        x-show="open"
                        x-cloak
                        x-transition
                        @click.outside="open = false"
                        class="absolute right-6 top-full z-50 mt-2 rounded-lg p-4 shadow-lg ring-1 ring-black/5 bg-(--wire-header-bg)"
                    >
                        <x-site.nav :items="$items" class="flex-col items-start gap-3" />
                    </div>
                @endif
            </div>
            @break

        @default
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
                <x-site.brand :logo="$logo" :brand="$brand" />
                <x-site.nav :items="$items" />
            </div>
    @endswitch
</header>
