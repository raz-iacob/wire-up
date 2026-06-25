@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks[".json_encode((string) $index)."].content";
    $items = data_get($block, 'content.items', []);
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" toolbar="bold italic underline | align" />
    <x-forms.texteditor-translated name="{{ $c }}.intro" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" toolbar="bold italic | align | link" />

    <div class="flex flex-col gap-2">
        <flux:label>{{ __('Sponsors') }}</flux:label>

        <div wire:sort="reorderSponsorItems" wire:sort:group="sponsors-{{ $block['id'] }}" class="flex flex-col gap-3">
            @foreach ($items as $i => $item)
                @php($itemTitle = \Illuminate\Support\Str::of((string) data_get($item, "name.{$locale}"))->squish()->limit(50)->value())
                <flux:card
                    size="sm"
                    class="p-0! overflow-hidden"
                    wire:key="sponsor-item-{{ $item['id'] ?? $i }}"
                    wire:sort:item="{{ $item['id'] ?? $i }}"
                    x-data="{ open: {{ $itemTitle === '' ? 'true' : 'false' }} }"
                >
                    <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                            <flux:icon name="bars-3" variant="mini" />
                        </div>

                        <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                            <flux:heading size="sm" class="truncate">{{ $itemTitle !== '' ? $itemTitle : __('Sponsor :number', ['number' => $i + 1]) }}</flux:heading>
                        </button>

                        <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                            <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                            </flux:button>
                            <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removeSponsorItem('{{ $block['id'] }}', {{ $i }})" :tooltip="__('Remove sponsor')" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 p-4" x-show="open" x-collapse x-cloak>
                        <livewire:admin.media-selector
                            wire:model="{{ $c }}.items.{{ $i }}.logo"
                            wire:key="sponsor-{{ $item['id'] ?? $i }}-logo"
                            name="sponsor-{{ $item['id'] ?? $i }}-logo"
                            type="image"
                            :locale="$locale"
                            :multiple="false"
                            label="{{ __('Logo') }}" />

                        <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.name" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Name') }}" />

                        <div class="grid md:grid-cols-2 gap-4">
                            <flux:input wire:model.lazy="{{ $c }}.items.{{ $i }}.link" type="url" label="{{ __('Link') }}" placeholder="https://example.com" />
                            <flux:input wire:model.lazy="{{ $c }}.items.{{ $i }}.tier" label="{{ __('Tier') }}" placeholder="{{ __('e.g. Gold') }}" />
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div>
            <flux:button size="sm" icon="plus" wire:click="addSponsorItem('{{ $block['id'] }}')">{{ __('Add sponsor') }}</flux:button>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <flux:select wire:model.live="{{ $c }}.layout" variant="listbox" label="{{ __('Layout') }}">
            <flux:select.option value="grid">{{ __('Grid') }}</flux:select.option>
            <flux:select.option value="marquee">{{ __('Marquee') }}</flux:select.option>
            <flux:select.option value="grouped">{{ __('Grouped by tier') }}</flux:select.option>
        </flux:select>

        <div x-show="{{ $b }}?.layout === 'grid'" x-cloak>
            <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
                @foreach ([3, 4, 5, 6] as $columnOption)
                    <flux:radio value="{{ $columnOption }}" label="{{ $columnOption }}" />
                @endforeach
            </flux:radio.group>
        </div>
    </div>

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.showNames" label="{{ __('Show sponsor names') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.grayscale" label="{{ __('Show logos in grayscale (color on hover)') }}" align="left" />
    </div>
</div>
