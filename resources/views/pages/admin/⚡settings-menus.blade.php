<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Page;
use App\Models\Settings;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

return new class extends Component
{
    public Settings $settings;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $header = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $footer = [];

    public int $seq = 0;

    #[Url(except: 'en')]
    public string $locale;

    /**
     * @var array<string, mixed>
     */
    public array $activeLocales = [];

    /**
     * @var array<int, array{id: int, title: string}>
     */
    public array $pages = [];

    public function mount(): void
    {
        $this->settings = Settings::current();
        $this->activeLocales = resolve('localization')->getActiveLocales();
        $this->locale = app()->getLocale();

        $meta = $this->settings->metadata ?? [];
        $this->header = $this->hydrateItems($meta['header_menu'] ?? []);
        $this->footer = $this->hydrateItems($meta['footer_menu'] ?? []);

        $this->pages = Page::query()
            ->published()
            ->with('translations')
            ->get()
            ->map(fn (Page $page): array => ['id' => $page->id, 'title' => $page->title !== '' ? $page->title : __('Untitled')])
            ->all();
    }

    public function addItem(string $menu): void
    {
        if (! in_array($menu, ['header', 'footer'], true)) {
            return;
        }

        $this->{$menu}[] = $this->defaultItem();
    }

    public function removeItem(string $menu, int $index): void
    {
        if (! in_array($menu, ['header', 'footer'], true) || ! isset($this->{$menu}[$index])) {
            return;
        }

        unset($this->{$menu}[$index]);
        $this->{$menu} = array_values($this->{$menu});
    }

    public function reorderHeader(string $key, int $position): void
    {
        $this->reorder('header', $key, $position);
    }

    public function reorderFooter(string $key, int $position): void
    {
        $this->reorder('footer', $key, $position);
    }

    public function update(UpdateSettingsAction $action): void
    {
        $validated = $this->validate($this->rules());

        $action->handle($this->settings, [
            'metadata' => [
                'header_menu' => $this->cleanItems($validated['header'] ?? []),
                'footer_menu' => $this->cleanItems($validated['footer'] ?? []),
            ],
        ]);

        Flux::toast(__('Menus have been updated.'), variant: 'success');
    }

    #[On('change-locale')]
    public function changeLocale(): void
    {
        $codes = array_keys($this->activeLocales);
        $index = array_search($this->locale, $codes, true);

        $this->locale = $codes[($index + 1) % count($codes)] ?? $this->locale;
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Menus'))
            ->layout('layouts::admin');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        $default = resolve('localization')->getDefaultLocale();
        $rules = [];

        foreach (['header', 'footer'] as $menu) {
            $rules[$menu] = ['array', 'max:20'];
            $rules["$menu.*.type"] = ['required', Rule::in(['page', 'link'])];
            $rules["$menu.*.appearance"] = ['required', Rule::in(['link', 'button'])];
            $rules["$menu.*.target"] = ['required', Rule::in(['_self', '_blank'])];
            $rules["$menu.*.page_id"] = ["required_if:$menu.*.type,page", 'nullable', 'integer', 'exists:pages,id'];
            $rules["$menu.*.url"] = ["required_if:$menu.*.type,link", 'nullable', 'string', 'url', 'max:255'];

            foreach (array_keys($this->activeLocales) as $code) {
                $rules["$menu.*.label.$code"] = $code === $default
                    ? ['required', 'string', 'max:100']
                    : ['nullable', 'string', 'max:100'];
            }
        }

        return $rules;
    }

    /**
     * @param  array<mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function hydrateItems(array $items): array
    {
        $hydrated = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $hydrated[] = [
                '_key' => (string) $this->seq++,
                'type' => in_array($item['type'] ?? null, ['page', 'link'], true) ? $item['type'] : 'page',
                'appearance' => in_array($item['appearance'] ?? null, ['link', 'button'], true) ? $item['appearance'] : 'link',
                'target' => in_array($item['target'] ?? null, ['_self', '_blank'], true) ? $item['target'] : '_self',
                'label' => [...$this->emptyLabel(), ...$this->onlyStringLabel($item['label'] ?? [])],
                'page_id' => isset($item['page_id']) ? (int) $item['page_id'] : null,
                'url' => is_string($item['url'] ?? null) ? $item['url'] : '',
                'open' => false,
            ];
        }

        return $hydrated;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultItem(): array
    {
        return [
            '_key' => (string) $this->seq++,
            'type' => 'page',
            'appearance' => 'link',
            'target' => '_self',
            'label' => $this->emptyLabel(),
            'page_id' => null,
            'url' => '',
            'open' => true,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emptyLabel(): array
    {
        return array_fill_keys(array_keys($this->activeLocales), '');
    }

    /**
     * @param  array<mixed>  $label
     * @return array<string, string>
     */
    private function onlyStringLabel(array $label): array
    {
        $clean = [];

        foreach ($label as $code => $value) {
            if (is_string($code) && is_string($value)) {
                $clean[$code] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function cleanItems(array $items): array
    {
        return array_map(fn (array $item): array => [
            'type' => $item['type'],
            'appearance' => $item['appearance'],
            'target' => $item['target'],
            'label' => $item['label'] ?? [],
            'page_id' => ($item['type'] ?? null) === 'page' ? ($item['page_id'] ?? null) : null,
            'url' => ($item['type'] ?? null) === 'link' ? ($item['url'] ?? '') : '',
        ], array_values($items));
    }

    private function reorder(string $menu, string $key, int $position): void
    {
        $items = $this->{$menu};
        $current = array_search($key, array_column($items, '_key'), true);

        if ($current === false) {
            return;
        }

        $item = $items[$current];
        unset($items[$current]);
        $items = array_values($items);
        array_splice($items, max(0, min($position, count($items))), 0, [$item]);

        $this->{$menu} = $items;
    }
};
?>

@php
    $multiLocale = count($activeLocales) > 1;
@endphp

<x-settings-layout :subheading="__('Manage your site’s navigation by customizing the header and footer menus.')">
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="max-w-3xl">
        <flux:tab.group>
            <div class="flex items-center justify-between gap-6">
                <flux:tabs variant="pills">
                    <flux:tab name="header">{{ __('Header') }}</flux:tab>
                    <flux:tab name="footer">{{ __('Footer') }}</flux:tab>
                </flux:tabs>

                @if ($multiLocale)
                    <div class="flex items-center gap-2">
                        <flux:text>{{ __('Edit in') }}</flux:text>
                        <flux:select wire:model.live="locale" size="sm" class="w-auto">
                            @foreach ($activeLocales as $code => $meta)
                                <flux:select.option value="{{ $code }}">{{ $meta['name'] ?? strtoupper($code) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif
            </div>

            @foreach (['header' => $header, 'footer' => $footer] as $menu => $items)
                <flux:tab.panel name="{{ $menu }}" class="space-y-4">
                    @if ($items !== [])
                        <div class="space-y-3" wire:sort="reorder{{ ucfirst($menu) }}">
                            @foreach ($items as $index => $item)
                                <x-admin.menu-item
                                    :menu="$menu"
                                    :index="$index"
                                    :item="$item"
                                    :locale="$locale"
                                    :multi-locale="$multiLocale"
                                    :pages="$pages"
                                />
                            @endforeach
                        </div>
                    @else
                        <flux:text>{{ __('No menu items yet. Add your first one below.') }}</flux:text>
                    @endif

                    <flux:button type="button" size="sm" icon="plus" wire:click="addItem('{{ $menu }}')">
                        {{ __('Add') }}
                    </flux:button>
                </flux:tab.panel>
            @endforeach
        </flux:tab.group>

        <div class="mt-10">
            <flux:button type="submit" variant="primary">
                {{ __('Update') }}
            </flux:button>
        </div>
    </form>
</x-settings-layout>
