<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Page;
use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<int, array{key: string, name: string, builtin: bool, display: array{background: bool, position: string, sticky: bool, mobile: string}, items: array<string, array<int, array<string, mixed>>>}>
     */
    public array $menus = [];

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

    public ?string $removeMenuKey = null;

    public ?int $removeIndex = null;

    public bool $showRemoveMenuModal = false;

    public ?string $removeMenuTarget = null;

    public function mount(): void
    {
        $this->activeLocales = resolve('localization')->getActiveLocales();

        if (! isset($this->locale) || ! array_key_exists($this->locale, $this->activeLocales)) {
            $this->locale = app()->getLocale();
        }

        foreach (SettingsService::current()->allMenus() as $menu) {
            $items = [];

            foreach (array_keys($this->activeLocales) as $code) {
                $items[$code] = $this->hydrateItems(is_array($menu['items'][$code] ?? null) ? $menu['items'][$code] : []);
            }

            $this->menus[] = [
                'key' => $menu['key'],
                'name' => $menu['name'],
                'builtin' => $menu['builtin'],
                'display' => $menu['display'],
                'items' => $items,
            ];
        }

        $this->pages = Page::query()
            ->published()
            ->with('translations')
            ->get()
            ->map(fn (Page $page): array => ['id' => $page->id, 'title' => $page->title !== '' ? $page->title : __('Untitled')])
            ->all();
    }

    public function addItem(string $key, string $type = 'page'): void
    {
        $index = $this->menuIndex($key);

        if ($index === null) {
            return;
        }

        $this->menus[$index]['items'][$this->locale][] = $this->defaultItem(in_array($type, ['page', 'link', 'heading'], true) ? $type : 'page');
    }

    public function addMenu(): void
    {
        $items = [];

        foreach (array_keys($this->activeLocales) as $code) {
            $items[$code] = [];
        }

        $this->menus[] = [
            'key' => (string) Str::uuid(),
            'name' => '',
            'builtin' => false,
            'display' => SettingsService::normalizeMenuDisplay(null),
            'items' => $items,
        ];
    }

    public function confirmRemove(string $key, int $index): void
    {
        $menuIndex = $this->menuIndex($key);

        if ($menuIndex === null || ! isset($this->menus[$menuIndex]['items'][$this->locale][$index])) {
            return;
        }

        $this->removeMenuKey = $key;
        $this->removeIndex = $index;
        $this->showRemoveModal = true;
    }

    public function removeConfirmed(): void
    {
        if ($this->removeMenuKey !== null && $this->removeIndex !== null) {
            $this->removeItem($this->removeMenuKey, $this->removeIndex);
        }

        $this->showRemoveModal = false;
        $this->removeMenuKey = null;
        $this->removeIndex = null;
    }

    public function removeItem(string $key, int $index): void
    {
        $menuIndex = $this->menuIndex($key);

        if ($menuIndex === null || ! isset($this->menus[$menuIndex]['items'][$this->locale][$index])) {
            return;
        }

        unset($this->menus[$menuIndex]['items'][$this->locale][$index]);
        $this->menus[$menuIndex]['items'][$this->locale] = array_values($this->menus[$menuIndex]['items'][$this->locale]);
    }

    public function confirmRemoveMenu(string $key): void
    {
        $index = $this->menuIndex($key);

        if ($index === null || $this->menus[$index]['builtin']) {
            return;
        }

        $this->removeMenuTarget = $key;
        $this->showRemoveMenuModal = true;
    }

    public function removeMenuConfirmed(): void
    {
        if ($this->removeMenuTarget !== null) {
            $index = $this->menuIndex($this->removeMenuTarget);

            if ($index !== null && ! $this->menus[$index]['builtin']) {
                unset($this->menus[$index]);
                $this->menus = array_values($this->menus);
            }
        }

        $this->showRemoveMenuModal = false;
        $this->removeMenuTarget = null;
    }

    public function reorder(string $key, int $position): void
    {
        foreach ($this->menus as $menuIndex => $menu) {
            $items = $menu['items'][$this->locale] ?? [];
            $current = array_search($key, array_column($items, '_key'), true);

            if ($current === false) {
                continue;
            }

            $item = $items[$current];
            unset($items[$current]);
            $items = array_values($items);
            array_splice($items, max(0, min($position, count($items))), 0, [$item]);

            $this->menus[$menuIndex]['items'][$this->locale] = $items;

            return;
        }
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
        $this->authorize('settings.edit');

        try {
            $this->validate($this->rules(), $this->validationMessages(), $this->validationAttributeNames());
        } catch (ValidationException $e) {
            $this->revealErrors($e);

            throw $e;
        }

        $payload = [];

        foreach ($this->menus as $menu) {
            $items = [];

            foreach (array_keys($this->activeLocales) as $code) {
                $items[$code] = $this->cleanItems($menu['items'][$code] ?? []);
            }

            $payload[] = [
                'key' => $menu['key'],
                'name' => mb_trim($menu['name']),
                'builtin' => $menu['builtin'],
                'display' => SettingsService::normalizeMenuDisplay($menu['display']),
                'items' => $items,
            ];
        }

        $action->handle(['menus' => $payload]);

        Flux::toast(__('Menus have been updated.'), variant: 'success');
    }

    private function menuIndex(string $key): ?int
    {
        foreach ($this->menus as $index => $menu) {
            if ($menu['key'] === $key) {
                return $index;
            }
        }

        return null;
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

        return [
            'menus.*.name.required' => __('Give this menu a name.'),
            'menus.*.items.*.*.label.required' => __('Give this menu item a label.'),
            'menus.*.items.*.*.page_id.required_if' => __('Choose a page for this menu item.'),
            'menus.*.items.*.*.page_id.exists' => __('The selected page is no longer available.'),
            'menus.*.items.*.*.url.required_if' => __('Enter a link for this menu item.'),
            'menus.*.items.*.*.url.regex' => $urlHint,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributeNames(): array
    {
        return [
            'menus.*.name' => __('menu name'),
            'menus.*.items.*.*.label' => __('label'),
            'menus.*.items.*.*.url' => __('link'),
            'menus.*.items.*.*.page_id' => __('page'),
            'menus.*.items.*.*.type' => __('type'),
            'menus.*.items.*.*.appearance' => __('appearance'),
            'menus.*.items.*.*.target' => __('target'),
        ];
    }

    private function revealErrors(ValidationException $e): void
    {
        $codes = array_keys($this->activeLocales);

        /** @var array<string, string> $erroredLocales */
        $erroredLocales = [];
        $keysToOpen = [];

        foreach (array_keys($e->errors()) as $errorKey) {
            $segments = explode('.', (string) $errorKey);

            if ($segments[0] !== 'menus') {
                continue;
            }

            if (($segments[2] ?? null) !== 'items') {
                continue;
            }

            if (count($segments) < 5) {
                continue;
            }

            $menuIndex = (int) $segments[1];
            $locale = $segments[3];
            $index = (int) $segments[4];

            if (! in_array($locale, $codes, true)) {
                continue;
            }

            $item = $this->menus[$menuIndex]['items'][$locale][$index] ?? null;

            if (! is_array($item)) {
                continue;
            }

            $this->menus[$menuIndex]['items'][$locale][$index]['open'] = true;
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

        foreach (array_keys($this->menus) as $i) {
            $rules["menus.$i.name"] = ['required', 'string', 'max:100'];
            $rules["menus.$i.display.background"] = ['boolean'];
            $rules["menus.$i.display.position"] = [Rule::in(['left', 'right'])];
            $rules["menus.$i.display.sticky"] = ['boolean'];
            $rules["menus.$i.display.mobile"] = [Rule::in(['collapse', 'hide', 'toggle'])];

            foreach (array_keys($this->activeLocales) as $locale) {
                $base = "menus.$i.items.$locale";

                $rules[$base] = ['array', 'max:20'];
                $rules["$base.*.type"] = ['required', Rule::in(['page', 'link', 'heading'])];
                $rules["$base.*.appearance"] = ['required', Rule::in(['link', 'button'])];
                $rules["$base.*.target"] = ['required', Rule::in(['_self', '_blank'])];
                $rules["$base.*.label"] = ['required', 'string', 'max:100'];
                $rules["$base.*.page_id"] = ["required_if:$base.*.type,page", 'nullable', 'integer', 'exists:pages,id'];
                $rules["$base.*.url"] = ["required_if:$base.*.type,link", 'nullable', 'string', 'max:255', 'regex:/^(https?:\/\/\S+|\/\S*|#\S+)$/'];
                $rules["$base.*.icon"] = ['nullable', 'string', Rule::in(config()->array('menu.icons'))];
                $rules["$base.*.badge"] = ['nullable', 'string', 'max:20'];
                $rules["$base.*.badgeColor"] = [Rule::in(config()->array('menu.badge_colors'))];
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
                'type' => in_array($item['type'] ?? null, ['page', 'link', 'heading'], true) ? $item['type'] : 'page',
                'appearance' => in_array($item['appearance'] ?? null, ['link', 'button'], true) ? $item['appearance'] : 'link',
                'target' => in_array($item['target'] ?? null, ['_self', '_blank'], true) ? $item['target'] : '_self',
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'page_id' => isset($item['page_id']) ? (int) $item['page_id'] : null,
                'url' => is_string($item['url'] ?? null) ? $item['url'] : '',
                'icon' => is_string($item['icon'] ?? null) ? $item['icon'] : '',
                'badge' => is_string($item['badge'] ?? null) ? $item['badge'] : '',
                'badgeColor' => is_string($item['badgeColor'] ?? null) ? $item['badgeColor'] : 'zinc',
                'open' => false,
            ];
        }

        return $hydrated;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultItem(string $type = 'page'): array
    {
        return [
            '_key' => (string) $this->seq++,
            'type' => $type,
            'appearance' => 'link',
            'target' => '_self',
            'label' => '',
            'page_id' => null,
            'url' => '',
            'icon' => '',
            'badge' => '',
            'badgeColor' => 'zinc',
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
            'icon' => $item['icon'] ?? '',
            'badge' => $item['badge'] ?? '',
            'badgeColor' => $item['badgeColor'] ?? 'zinc',
        ], array_values($items));
    }
};
?>

@php
    $multiLocale = count($activeLocales) > 1;
    $builtinMenus = collect($menus)->where('builtin', true);
    $customMenus = collect($menus)->reject(fn (array $menu): bool => $menu['builtin']);
@endphp

<x-admin.settings-layout>
    <form
        wire:submit="update"
        wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="grid md:grid-cols-5 gap-10 items-start"
    >
        <div class="space-y-10 md:col-span-3">
            {{-- Built-in menus --}}
            @foreach ($builtinMenus as $i => $menu)
                <x-admin.menu-builder-section :menu="$menu" :index="$i" :locale="$locale" :pages="$pages" :multi-locale="$multiLocale" />
            @endforeach

            {{-- Custom menus --}}
            <div>
                <div class="flex items-center gap-3">
                    <flux:label>{{ __('Custom menus') }}</flux:label>
                    @if ($multiLocale)
                        <x-admin.locale-switcher :locale="$locale" />
                    @endif
                </div>

                <div class="divide-y-2 divide-gray-200 dark:divide-white/20">
                    @forelse ($customMenus as $i => $menu)
                        <x-admin.menu-builder-section :menu="$menu" :index="$i" :locale="$locale" :pages="$pages" :multi-locale="$multiLocale" />
                    @empty
                        <flux:text>{{ __('No custom menus yet. Add your first one below.') }}</flux:text>
                    @endforelse
                </div>

                <flux:button type="button" icon="plus" wire:click="addMenu">
                    {{ __('Add menu') }}
                </flux:button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Update') }}
                </flux:button>
            </div>
        </div>
    </form>

    @php
        $removingItem = ($removeMenuKey !== null && $removeIndex !== null)
            ? (collect($menus)->firstWhere('key', $removeMenuKey)['items'][$locale][$removeIndex] ?? null)
            : null;
        $removingLabel = is_array($removingItem) ? ($removingItem['label'] ?? '') : '';
        $removingMenu = $removeMenuTarget !== null ? collect($menus)->firstWhere('key', $removeMenuTarget) : null;
        $removingMenuName = is_array($removingMenu) ? ($removingMenu['name'] ?? '') : '';
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

    <flux:modal wire:model.self="showRemoveMenuModal" class="min-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete menu?') }}</flux:heading>
                <flux:text class="mt-2">
                    @if ($removingMenuName !== '')
                        {{ __('":name" and all of its items will be deleted.', ['name' => $removingMenuName]) }}
                    @else
                        {{ __('This menu and all of its items will be deleted.') }}
                    @endif
                </flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="removeMenuConfirmed">{{ __('Delete') }}</flux:button>
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
