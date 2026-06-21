@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $items = data_get($block, 'content.items', []);
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" toolbar="bold italic underline" />

    <div class="flex flex-col gap-2">
        <flux:label>{{ __('Items') }}</flux:label>

        <div wire:sort="reorderAccordionItems" wire:sort:group="accordion-{{ $block['id'] }}" class="flex flex-col gap-3">
            @foreach ($items as $i => $item)
                @php($itemTitle = \Illuminate\Support\Str::of(strip_tags((string) data_get($item, "title.{$locale}")))->squish()->limit(50)->value())
                <flux:card
                    size="sm"
                    class="p-0! overflow-hidden"
                    wire:key="acc-item-{{ $item['id'] ?? $i }}"
                    wire:sort:item="{{ $item['id'] ?? $i }}"
                    x-data="{ open: {{ $itemTitle === '' ? 'true' : 'false' }} }"
                >
                    <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                            <flux:icon name="bars-3" variant="mini" />
                        </div>

                        <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                            <flux:heading size="sm" class="truncate">{{ $itemTitle !== '' ? $itemTitle : __('Item :number', ['number' => $i + 1]) }}</flux:heading>
                        </button>

                        <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                            <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                            </flux:button>
                            <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removeAccordionItem('{{ $block['id'] }}', {{ $i }})" :tooltip="__('Remove item')" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 p-4" x-show="open" x-collapse x-cloak>
                        <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.title" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Title') }}" />
                        <x-forms.texteditor-translated name="{{ $c }}.items.{{ $i }}.body" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Content') }}" toolbar="bold italic | bullet ordered | link" />
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div>
            <flux:button size="sm" icon="plus" wire:click="addAccordionItem('{{ $block['id'] }}')">{{ __('Add item') }}</flux:button>
        </div>
    </div>

    <flux:radio.group wire:model.live="{{ $c }}.icon" variant="segmented" label="{{ __('Indicator') }}">
        <flux:radio value="chevron" label="{{ __('Chevron') }}" />
        <flux:radio value="plus-minus" label="{{ __('Plus / minus') }}" />
    </flux:radio.group>

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.lazy="{{ $c }}.exclusive" label="{{ __('Only one item open at a time') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
    </div>
</div>
