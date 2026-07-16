@props([
    'menu',
    'index',
    'locale',
    'pages' => [],
    'multiLocale' => false,
])

@php
    $items = $menu['items'][$locale] ?? [];
@endphp

<section wire:key="menu-{{ $menu['key'] }}" x-data="{ design: false }"
    @class([ 'pb-6' => $menu['builtin'], 'py-6' => ! $menu['builtin']])>
    <div class="flex items-center justify-between gap-3 mb-2">
        @if ($menu['builtin'])
            <div class="flex items-center gap-3">
                <flux:label>{{ __(ucfirst($menu['key'])) }} {{ __('menu') }}</flux:label>
                @if ($multiLocale)
                    <x-admin.locale-switcher :locale="$locale" />
                @endif
            </div>
        @else
            <flux:label>{{ __('Name') }}:</flux:label>
            <div class="flex-1">
                <flux:input wire:model.blur="menus.{{ $index }}.name" size="sm" placeholder="{{ __('e.g. Main Menu') }}" class="max-w-xs" />
                <flux:error name="menus.{{ $index }}.name" />
            </div>
            <flux:button type="button" size="sm" variant="subtle" icon="cog-6-tooth" :tooltip="__('Design options')" x-on:click="design = ! design" x-bind:data-active="design" class="data-[active=true]:text-accent" />
            <flux:button type="button" size="sm" variant="subtle" icon="trash" :tooltip="__('Delete menu')" wire:click="confirmRemoveMenu('{{ $menu['key'] }}')" />
        @endif
    </div>

    @unless ($menu['builtin'])
        <div class="mb-4 rounded-lg border border-zinc-200 dark:border-white/10 p-4" x-show="design" x-cloak x-collapse>
            <div class="flex flex-col gap-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <flux:select wire:model="menus.{{ $index }}.display.position" label="{{ __('Position') }}">
                        <flux:select.option value="left">{{ __('Left') }}</flux:select.option>
                        <flux:select.option value="right">{{ __('Right') }}</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="menus.{{ $index }}.display.mobile" label="{{ __('On mobile') }}">
                        <flux:select.option value="collapse">{{ __('Stack above content') }}</flux:select.option>
                        <flux:select.option value="toggle">{{ __('Toggleable') }}</flux:select.option>
                        <flux:select.option value="hide">{{ __('Hide') }}</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <flux:switch wire:model="menus.{{ $index }}.display.background" label="{{ __('Background panel') }}" align="left" />
                    <flux:switch wire:model="menus.{{ $index }}.display.sticky" label="{{ __('Sticky on scroll') }}" align="left" />
                </div>
            </div>
        </div>
    @endunless

    @if ($items !== [])
        <div class="space-y-3" wire:sort="reorder" wire:key="items-{{ $menu['key'] }}-{{ implode('.', array_column($items, '_key')) }}">
            @foreach ($items as $itemIndex => $item)
                <x-admin.menu-item
                    :base="'menus.'.$index.'.items.'.$locale.'.'.$itemIndex"
                    :menu-key="$menu['key']"
                    :index="$itemIndex"
                    :item="$item"
                    :pages="$pages"
                    :sidebar-fields="! $menu['builtin']"
                />
            @endforeach
        </div>
    @else
        <div class="mt-4">
            <flux:text>{{ __('No menu items yet. Add your first one below.') }}</flux:text>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <flux:button type="button" size="sm" icon="plus" wire:click="addItem('{{ $menu['key'] }}')">
            {{ __('Add item') }}
        </flux:button>

        <flux:button type="button" size="sm" variant="subtle" icon="user-circle" wire:click="addItem('{{ $menu['key'] }}', 'account')">
            {{ __('Add account link') }}
        </flux:button>

        @unless ($menu['builtin'])
            <flux:button type="button" size="sm" variant="subtle" icon="bars-3-bottom-left" wire:click="addItem('{{ $menu['key'] }}', 'heading')">
                {{ __('Add group heading') }}
            </flux:button>
        @endunless
    </div>
</section>
