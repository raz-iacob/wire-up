@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $items = data_get($block, 'content.items', []);
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:label>{{ __('Buttons') }}</flux:label>

        <div wire:sort="reorderButtonItems" wire:sort:group="buttons-{{ $block['id'] }}" class="flex flex-col gap-3">
            @foreach ($items as $i => $item)
                @php($buttonTitle = \Illuminate\Support\Str::of((string) data_get($item, "text.{$locale}"))->squish()->limit(50)->value())
                <flux:card
                    size="sm"
                    class="p-0! overflow-hidden"
                    wire:key="button-item-{{ $item['id'] ?? $i }}"
                    wire:sort:item="{{ $item['id'] ?? $i }}"
                    x-data="{ open: false }"
                    x-on:open-block-item.window="$event.detail.id === '{{ $item['id'] ?? $i }}' && (open = true)"
                >
                    <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                            <flux:icon name="bars-3" variant="mini" />
                        </div>

                        <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                            <flux:heading size="sm" class="truncate">{{ $buttonTitle !== '' ? $buttonTitle : __('Button :number', ['number' => $i + 1]) }}</flux:heading>
                        </button>

                        <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                            <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                            </flux:button>
                            <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removeButtonItem('{{ $block['id'] }}', {{ $i }})" :tooltip="__('Remove button')" />
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4 p-4" x-show="open" x-collapse x-cloak>
                        <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.text" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Button text') }}" />

                        <flux:select wire:model.live="{{ $c }}.items.{{ $i }}.variant" variant="listbox" label="{{ __('Style') }}">
                            <flux:select.option value="primary">{{ __('Primary') }}</flux:select.option>
                            <flux:select.option value="secondary">{{ __('Secondary') }}</flux:select.option>
                            <flux:select.option value="outline">{{ __('Outline') }}</flux:select.option>
                        </flux:select>

                        <flux:select wire:model.live="{{ $c }}.items.{{ $i }}.link.type" variant="listbox" label="{{ __('Link to') }}">
                            <flux:select.option value="page">{{ __('A page') }}</flux:select.option>
                            <flux:select.option value="url">{{ __('External URL') }}</flux:select.option>
                            <flux:select.option value="anchor">{{ __('Section on this page') }}</flux:select.option>
                        </flux:select>

                        @php($linkType = data_get($item, 'link.type', 'url'))
                        <div class="md:col-span-2">
                            @if ($linkType === 'page')
                                <flux:select wire:model="{{ $c }}.items.{{ $i }}.link.value" variant="listbox" searchable placeholder="{{ __('Choose a page') }}" label="{{ __('Page') }}">
                                    @foreach ($pageOptions as $pageId => $pageTitle)
                                        <flux:select.option value="{{ $pageId }}">{{ $pageTitle }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @elseif ($linkType === 'anchor')
                                <flux:input wire:model.lazy="{{ $c }}.items.{{ $i }}.link.value" label="{{ __('Section anchor') }}" placeholder="#contact" />
                            @else
                                <div class="flex flex-col gap-3">
                                    <flux:input wire:model.lazy="{{ $c }}.items.{{ $i }}.link.value" label="{{ __('URL') }}" placeholder="https://example.com" />
                                    <flux:switch wire:model.lazy="{{ $c }}.items.{{ $i }}.link.newTab" label="{{ __('Open in a new tab') }}" align="left" />
                                </div>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        @if (count($items) < 3)
            <div>
                <flux:button size="sm" icon="plus" wire:click="addButtonItem('{{ $block['id'] }}')">{{ __('Add button') }}</flux:button>
            </div>
        @endif
    </div>

    <flux:radio.group wire:model.lazy="{{ $c }}.align" label="{{ __('Alignment') }}" variant="segmented">
        <flux:radio value="left" icon="bars-3-bottom-left" label="{{ __('Left') }}" />
        <flux:radio value="center" icon="bars-3" label="{{ __('Center') }}" />
        <flux:radio value="right" icon="bars-3-bottom-right" label="{{ __('Right') }}" />
    </flux:radio.group>

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
