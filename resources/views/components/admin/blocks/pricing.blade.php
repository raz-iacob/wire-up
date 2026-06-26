@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks[".json_encode((string) $index)."].content";
    $items = data_get($block, 'content.items', []);
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.intro" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" />

    <div class="flex flex-col gap-2">
        <flux:label>{{ __('Plans') }}</flux:label>

        <div wire:sort="reorderPricingItems" wire:sort:group="pricing-{{ $block['id'] }}" class="flex flex-col gap-3">
            @foreach ($items as $i => $item)
                @php($planName = \Illuminate\Support\Str::of((string) data_get($item, "name.{$locale}"))->squish()->limit(50)->value())
                <flux:card
                    size="sm"
                    class="p-0! overflow-hidden"
                    wire:key="pricing-item-{{ $item['id'] ?? $i }}"
                    wire:sort:item="{{ $item['id'] ?? $i }}"
                    x-data="{ open: false }"
                    x-on:open-block-item.window="$event.detail.id === '{{ $item['id'] ?? $i }}' && (open = true)"
                >
                    <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                            <flux:icon name="bars-3" variant="mini" />
                        </div>

                        <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                            <flux:heading size="sm" class="truncate">{{ $planName !== '' ? $planName : __('Plan :number', ['number' => $i + 1]) }}</flux:heading>
                        </button>

                        <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                            <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                            </flux:button>
                            <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removePricingItem('{{ $block['id'] }}', {{ $i }})" :tooltip="__('Remove plan')" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 p-4" x-show="open" x-collapse x-cloak>
                        <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.name" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Plan name') }}" />

                        <div class="grid md:grid-cols-2 gap-4">
                            <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.price" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Price') }}" />
                            <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.period" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Period') }}" />
                        </div>

                        <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.description" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Short description') }}" />

                        <x-forms.texteditor-translated name="{{ $c }}.items.{{ $i }}.features" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Features') }}" />

                        <flux:switch wire:model.live="{{ $c }}.items.{{ $i }}.featured" label="{{ __('Highlight this plan') }}" align="left" />

                        <div x-show="{{ $b }}?.items?.[{{ $i }}]?.featured" x-cloak>
                            <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.badge" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Highlight label') }}" />
                        </div>

                        <flux:separator variant="subtle" />

                        <flux:switch wire:model.live="{{ $c }}.items.{{ $i }}.cta.enabled" label="{{ __('Show a button') }}" align="left" />

                        <div x-show="{{ $b }}?.items?.[{{ $i }}]?.cta?.enabled" class="grid md:grid-cols-2 gap-4">
                            <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.cta.text" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Button text') }}" />

                            <flux:select wire:model.live="{{ $c }}.items.{{ $i }}.cta.link.type" variant="listbox" label="{{ __('Link to') }}">
                                <flux:select.option value="page">{{ __('A page') }}</flux:select.option>
                                <flux:select.option value="url">{{ __('External URL') }}</flux:select.option>
                                <flux:select.option value="anchor">{{ __('Section on this page') }}</flux:select.option>
                            </flux:select>

                            @php($linkType = data_get($item, 'cta.link.type', 'url'))
                            <div class="col-span-2">
                                @if ($linkType === 'page')
                                    <flux:select wire:model="{{ $c }}.items.{{ $i }}.cta.link.value" variant="listbox" searchable placeholder="{{ __('Choose a page') }}" label="{{ __('Page') }}">
                                        @foreach ($pageOptions as $pageId => $pageTitle)
                                            <flux:select.option value="{{ $pageId }}">{{ $pageTitle }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @elseif ($linkType === 'anchor')
                                    <flux:input wire:model.lazy="{{ $c }}.items.{{ $i }}.cta.link.value" label="{{ __('Section anchor') }}" placeholder="#contact" />
                                @else
                                    <div class="flex flex-col gap-3">
                                        <flux:input wire:model.lazy="{{ $c }}.items.{{ $i }}.cta.link.value" label="{{ __('URL') }}" placeholder="https://example.com" />
                                        <flux:switch wire:model.lazy="{{ $c }}.items.{{ $i }}.cta.link.newTab" label="{{ __('Open in a new tab') }}" align="left" />
                                    </div>
                                @endif
                            </div>

                            <flux:color-picker wire:model="{{ $c }}.items.{{ $i }}.cta.bg" clearable label="{{ __('Button color') }}" placeholder="{{ __('Theme') }}" />
                            <flux:color-picker wire:model="{{ $c }}.items.{{ $i }}.cta.textColor" clearable label="{{ __('Text color') }}" placeholder="{{ __('Theme') }}" />
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div>
            <flux:button size="sm" icon="plus" wire:click="addPricingItem('{{ $block['id'] }}')">{{ __('Add plan') }}</flux:button>
        </div>
    </div>

    <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
        @foreach ([2, 3, 4] as $columnOption)
            <flux:radio value="{{ $columnOption }}" label="{{ $columnOption }}" />
        @endforeach
    </flux:radio.group>

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
