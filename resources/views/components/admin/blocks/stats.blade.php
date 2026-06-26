@props(['block', 'locale', 'multiLocale' => false, 'index'])

@php
    $c = "blocks.{$index}.content";
    $items = data_get($block, 'content.items', []);
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.intro" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" />

    <div class="flex flex-col gap-2">
        <flux:label>{{ __('Stats') }}</flux:label>

        <div wire:sort="reorderStatItems" wire:sort:group="stats-{{ $block['id'] }}" class="flex flex-col gap-3">
            @foreach ($items as $i => $item)
                @php($statTitle = \Illuminate\Support\Str::of((string) data_get($item, "label.{$locale}"))->squish()->limit(50)->value())
                <flux:card
                    size="sm"
                    class="p-0! overflow-hidden"
                    wire:key="stat-item-{{ $item['id'] ?? $i }}"
                    wire:sort:item="{{ $item['id'] ?? $i }}"
                    x-data="{ open: false }"
                    x-on:open-block-item.window="$event.detail.id === '{{ $item['id'] ?? $i }}' && (open = true)"
                >
                    <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                            <flux:icon name="bars-3" variant="mini" />
                        </div>

                        <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                            <flux:heading size="sm" class="truncate">{{ $statTitle !== '' ? $statTitle : __('Stat :number', ['number' => $i + 1]) }}</flux:heading>
                        </button>

                        <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                            <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                            </flux:button>
                            <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removeStatItem('{{ $block['id'] }}', {{ $i }})" :tooltip="__('Remove stat')" />
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4 p-4" x-show="open" x-collapse x-cloak>
                        <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.label" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Label') }}" />
                        <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.value" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Value') }}" />
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div>
            <flux:button size="sm" icon="plus" wire:click="addStatItem('{{ $block['id'] }}')">{{ __('Add stat') }}</flux:button>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <flux:radio.group wire:model.live="{{ $c }}.layout" variant="segmented" label="{{ __('Layout') }}">
            <flux:radio value="plain" label="{{ __('Plain') }}" />
            <flux:radio value="cards" label="{{ __('Cards') }}" />
            <flux:radio value="dividers" label="{{ __('Dividers') }}" />
        </flux:radio.group>

        <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
            @foreach ([2, 3, 4] as $columnOption)
                <flux:radio value="{{ $columnOption }}" label="{{ $columnOption }}" />
            @endforeach
        </flux:radio.group>
    </div>

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
