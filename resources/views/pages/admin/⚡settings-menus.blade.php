<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Page;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    public array $header = [];

    /**
     * @var array<string, array<int, array<string, mixed>>>
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

    public bool $showRemoveModal = false;

    public ?string $removeMenu = null;

    public ?int $removeIndex = null;

    public function mount(): void
    {
        $this->activeLocales = resolve('localization')->getActiveLocales();

        if (! isset($this->locale) || ! array_key_exists($this->locale, $this->activeLocales)) {
            $this->locale = app()->getLocale();
        }

        $header = is_array(config('site.header_menu')) ? config('site.header_menu') : [];
        $footer = is_array(config('site.footer_menu')) ? config('site.footer_menu') : [];

        foreach (array_keys($this->activeLocales) as $code) {
            $this->header[$code] = $this->hydrateItems(is_array($header[$code] ?? null) ? $header[$code] : []);
            $this->footer[$code] = $this->hydrateItems(is_array($footer[$code] ?? null) ? $footer[$code] : []);
        }

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

        $this->{$menu}[$this->locale][] = $this->defaultItem();
    }

    public function confirmRemove(string $menu, int $index): void
    {
        if (! in_array($menu, ['header', 'footer'], true) || ! isset($this->{$menu}[$this->locale][$index])) {
            return;
        }

        $this->removeMenu = $menu;
        $this->removeIndex = $index;
        $this->showRemoveModal = true;
    }

    public function removeConfirmed(): void
    {
        if ($this->removeMenu !== null && $this->removeIndex !== null) {
            $this->removeItem($this->removeMenu, $this->removeIndex);
        }

        $this->showRemoveModal = false;
        $this->removeMenu = null;
        $this->removeIndex = null;
    }

    public function removeItem(string $menu, int $index): void
    {
        if (! in_array($menu, ['header', 'footer'], true) || ! isset($this->{$menu}[$this->locale][$index])) {
            return;
        }

        unset($this->{$menu}[$this->locale][$index]);
        $this->{$menu}[$this->locale] = array_values($this->{$menu}[$this->locale]);
    }

    public function reorderHeader(string $key, int $position): void
    {
        $this->reorder('header', $key, $position);
    }

    public function reorderFooter(string $key, int $position): void
    {
        $this->reorder('footer', $key, $position);
    }

    #[On('change-locale')]
    public function changeLocale(): void
    {
        $codes = array_keys($this->activeLocales);
        $index = array_search($this->locale, $codes, true);

        $this->locale = $codes[($index + 1) % count($codes)] ?? $this->locale;
    }

    public function update(UpdateSettingsAction $action): void
    {
        try {
            $this->validate($this->rules(), $this->validationMessages(), $this->validationAttributeNames());
        } catch (ValidationException $e) {
            $this->revealErrors($e);

            throw $e;
        }

        $header = [];
        $footer = [];

        foreach (array_keys($this->activeLocales) as $code) {
            $header[$code] = $this->cleanItems($this->header[$code] ?? []);
            $footer[$code] = $this->cleanItems($this->footer[$code] ?? []);
        }

        $action->handle(['header_menu' => $header, 'footer_menu' => $footer]);

        Flux::toast(__('Menus have been updated.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Menus'))
            ->layout('layouts::admin');
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        $urlHint = __('Enter a full URL (https://…), a path (/about), or an anchor link (#contact).');

        $messages = [];

        foreach (['header', 'footer'] as $menu) {
            $messages["$menu.*.*.label.required"] = __('Give this menu item a label.');
            $messages["$menu.*.*.page_id.required_if"] = __('Choose a page for this menu item.');
            $messages["$menu.*.*.page_id.exists"] = __('The selected page is no longer available.');
            $messages["$menu.*.*.url.required_if"] = __('Enter a link for this menu item.');
            $messages["$menu.*.*.url.regex"] = $urlHint;
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributeNames(): array
    {
        $attributes = [];

        foreach (['header', 'footer'] as $menu) {
            $attributes["$menu.*.*.label"] = __('label');
            $attributes["$menu.*.*.url"] = __('link');
            $attributes["$menu.*.*.page_id"] = __('page');
            $attributes["$menu.*.*.type"] = __('type');
            $attributes["$menu.*.*.appearance"] = __('appearance');
            $attributes["$menu.*.*.target"] = __('target');
        }

        return $attributes;
    }

    private function revealErrors(ValidationException $e): void
    {
        $codes = array_keys($this->activeLocales);

        /** @var array<string, string> $erroredLocales */
        $erroredLocales = [];
        $keysToOpen = [];

        foreach (array_keys($e->errors()) as $errorKey) {
            $segments = explode('.', (string) $errorKey);

            if (count($segments) < 3) {
                continue;
            }

            [$menu, $locale, $index] = $segments;
            if (! in_array($menu, ['header', 'footer'], true)) {
                continue;
            }
            if (! in_array($locale, $codes, true)) {
                continue;
            }

            $item = $this->{$menu}[$locale][(int) $index] ?? null;

            if (! is_array($item)) {
                continue;
            }

            $this->{$menu}[$locale][(int) $index]['open'] = true;
            $erroredLocales[$locale] ??= $locale;
            $keysToOpen[] = $item['_key'];
        }

        if ($erroredLocales !== [] && ! isset($erroredLocales[$this->locale])) {
            $this->locale = reset($erroredLocales);
        }

        $this->dispatch('menu-errors-revealed', keys: $keysToOpen);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        $rules = [];

        foreach (['header', 'footer'] as $menu) {
            foreach (array_keys($this->activeLocales) as $locale) {
                $rules["$menu.$locale"] = ['array', 'max:20'];
                $rules["$menu.$locale.*.type"] = ['required', Rule::in(['page', 'link'])];
                $rules["$menu.$locale.*.appearance"] = ['required', Rule::in(['link', 'button'])];
                $rules["$menu.$locale.*.target"] = ['required', Rule::in(['_self', '_blank'])];
                $rules["$menu.$locale.*.label"] = ['required', 'string', 'max:100'];
                $rules["$menu.$locale.*.page_id"] = ["required_if:$menu.$locale.*.type,page", 'nullable', 'integer', 'exists:pages,id'];
                $rules["$menu.$locale.*.url"] = ["required_if:$menu.$locale.*.type,link", 'nullable', 'string', 'max:255', 'regex:/^(https?:\/\/\S+|\/\S*|#\S+)$/'];
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
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
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
            'label' => '',
            'page_id' => null,
            'url' => '',
            'open' => true,
        ];
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
            'label' => $item['label'] ?? '',
            'page_id' => ($item['type'] ?? null) === 'page' ? ($item['page_id'] ?? null) : null,
            'url' => ($item['type'] ?? null) === 'link' ? ($item['url'] ?? '') : '',
        ], array_values($items));
    }

    private function reorder(string $menu, string $key, int $position): void
    {
        $items = $this->{$menu}[$this->locale] ?? [];
        $current = array_search($key, array_column($items, '_key'), true);

        if ($current === false) {
            return;
        }

        $item = $items[$current];
        unset($items[$current]);
        $items = array_values($items);
        array_splice($items, max(0, min($position, count($items))), 0, [$item]);

        $this->{$menu}[$this->locale] = $items;
    }
};
?>

@php
    $multiLocale = count($activeLocales) > 1;
    $currentHeader = $header[$locale] ?? [];
    $currentFooter = $footer[$locale] ?? [];
@endphp

<x-admin.settings-layout>
    <form
        wire:submit="update"
        wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="grid md:grid-cols-5 gap-10 items-start"
    >
        <div class="space-y-10 md:col-span-3">
            @foreach (['header' => $currentHeader, 'footer' => $currentFooter] as $menu => $items)
                <section>
                    <div class="flex items-center gap-3 mb-2">
                        <flux:label>{{ $menu === 'header' ? __('Header') : __('Footer') }} {{ __('menu') }}</flux:label>

                        @if ($multiLocale)
                            <flux:tooltip content="{{ __('Change language') }}">
                                <flux:badge size="sm" class="text-xs py-0.5!" as="button" inset="top bottom" x-on:click="$wire.dispatch('change-locale')">{{ strtoupper($locale) }}</flux:badge>
                            </flux:tooltip>
                        @endif
                    </div>

                    @if ($items !== [])
                        <div class="space-y-3" wire:sort="reorder{{ ucfirst($menu) }}">
                            @foreach ($items as $index => $item)
                                <x-admin.menu-item
                                    :menu="$menu"
                                    :index="$index"
                                    :item="$item"
                                    :locale="$locale"
                                    :pages="$pages"
                                />
                            @endforeach
                        </div>
                    @else
                        <div class="mt-4">
                            <flux:text>{{ __('No menu items yet. Add your first one below.') }}</flux:text>
                        </div>
                    @endif

                    <flux:button type="button" size="sm" icon="plus" class="mt-6" wire:click="addItem('{{ $menu }}')">
                        {{ __('Add') }}
                    </flux:button>
                </section>
            @endforeach

            <div>
                <flux:button type="submit" variant="primary">
                    {{ __('Update') }}
                </flux:button>
            </div>
        </div>
    </form>

    @php
        $removing = ($removeMenu !== null && $removeIndex !== null)
            ? (($removeMenu === 'header' ? $header : $footer)[$locale][$removeIndex] ?? null)
            : null;
        $removingLabel = is_array($removing) ? ($removing['label'] ?? '') : '';
    @endphp
    <flux:modal wire:model.self="showRemoveModal" class="min-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove menu item?') }}</flux:heading>
                <flux:text class="mt-2">
                    @if ($removingLabel !== '')
                        {{ __('":name" will be removed from this menu.', ['name' => $removingLabel]) }}
                    @else
                        {{ __('This item will be removed from this menu.') }}
                    @endif
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="removeConfirmed">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ __('Menus') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Menus') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
