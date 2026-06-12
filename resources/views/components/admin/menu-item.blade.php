@props([
    'menu',
    'index',
    'item',
    'locale',
    'multiLocale' => false,
    'pages' => [],
])

@php
    $base = $menu.'.'.$index;
@endphp

<div
    wire:key="{{ $menu }}-{{ $item['_key'] }}"
    wire:sort:item="{{ $item['_key'] }}"
    x-data="{ open: @js((bool) ($item['open'] ?? false)) }"
    class="overflow-hidden rounded-lg border border-zinc-200 dark:border-white/10"
>
    <div class="flex items-center gap-2 bg-zinc-50 dark:bg-white/5 py-1.5 pl-2 pr-1.5">
        <button
            type="button"
            wire:sort:handle
            aria-label="{{ __('Drag to reorder') }}"
            class="shrink-0 cursor-grab text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
        >
            <flux:icon name="bars-3" class="size-5" />
        </button>

        <button type="button" x-on:click="open = ! open" class="min-w-0 flex-1 text-left">
            <flux:heading class="truncate text-sm!" data-test="menu-item-title">
                {{ ($item['label'][$locale] ?? '') !== '' ? $item['label'][$locale] : __('New Item') }}
            </flux:heading>
        </button>

        <flux:button size="sm" variant="subtle" square x-on:click="open = ! open" :tooltip="__('Toggle')">
            <flux:icon name="chevron-down" variant="micro" x-show="! open" />
            <flux:icon name="chevron-up" variant="micro" x-show="open" x-cloak />
        </flux:button>

        <flux:button size="sm" variant="subtle" square icon="x-mark" :tooltip="__('Remove')" wire:click="removeItem('{{ $menu }}', {{ $index }})" />
    </div>

    <div class="grid gap-4 p-4 sm:grid-cols-2" x-show="open" x-cloak>
        <flux:field>
            <flux:label>{{ __('Type') }}</flux:label>
            <flux:select wire:model.live="{{ $base }}.type">
                <flux:select.option value="page">{{ __('Page') }}</flux:select.option>
                <flux:select.option value="link">{{ __('Custom Link') }}</flux:select.option>
            </flux:select>
            <flux:error name="{{ $base }}.type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Appearance') }}</flux:label>
            <flux:select wire:model="{{ $base }}.appearance">
                <flux:select.option value="link">{{ __('Link') }}</flux:select.option>
                <flux:select.option value="button">{{ __('Button') }}</flux:select.option>
            </flux:select>
            <flux:error name="{{ $base }}.appearance" />
        </flux:field>

        <x-forms.input-translated
            name="{{ $base }}.label"
            :locale="$locale"
            :multi-locale="$multiLocale"
            label="{{ __('Menu Label') }}"
            :required="true"
        />

        <flux:field>
            <flux:label>{{ __('Target') }}</flux:label>
            <flux:select wire:model="{{ $base }}.target">
                <flux:select.option value="_self">{{ __('Same Window') }}</flux:select.option>
                <flux:select.option value="_blank">{{ __('New Window') }}</flux:select.option>
            </flux:select>
            <flux:error name="{{ $base }}.target" />
        </flux:field>

        @if (($item['type'] ?? 'page') === 'link')
            <div class="sm:col-span-2">
                <flux:field>
                    <flux:label>{{ __('URL') }}</flux:label>
                    <flux:input wire:model="{{ $base }}.url" type="url" placeholder="https://example.com" />
                    <flux:error name="{{ $base }}.url" />
                </flux:field>
            </div>
        @else
            <div class="sm:col-span-2">
                <flux:field>
                    <flux:label>{{ __('Select Page') }}</flux:label>
                    <flux:select variant="listbox" wire:model="{{ $base }}.page_id" placeholder="{{ __('Choose a page...') }}">
                        @foreach ($pages as $page)
                            <flux:select.option :value="$page['id']">{{ $page['title'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="{{ $base }}.page_id" />
                </flux:field>
            </div>
        @endif
    </div>
</div>
