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
        <flux:label>{{ __('Cards') }}</flux:label>

        <div wire:sort="reorderFeatureItems" wire:sort:group="feature-cards-{{ $block['id'] }}" class="flex flex-col gap-3">
            @foreach ($items as $i => $item)
                @php($itemTitle = \Illuminate\Support\Str::of((string) data_get($item, "title.{$locale}"))->squish()->limit(50)->value())
                <flux:card
                    size="sm"
                    class="p-0! overflow-hidden"
                    wire:key="feature-item-{{ $item['id'] ?? $i }}"
                    wire:sort:item="{{ $item['id'] ?? $i }}"
                    x-data="{ open: {{ $itemTitle === '' ? 'true' : 'false' }} }"
                >
                    <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                            <flux:icon name="bars-3" variant="mini" />
                        </div>

                        <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                            <flux:heading size="sm" class="truncate">{{ $itemTitle !== '' ? $itemTitle : __('Card :number', ['number' => $i + 1]) }}</flux:heading>
                        </button>

                        <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                            <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                            </flux:button>
                            <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removeFeatureItem('{{ $block['id'] }}', {{ $i }})" :tooltip="__('Remove card')" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 p-4" x-show="open" x-collapse x-cloak>
                        <livewire:admin.media-selector
                            wire:model="{{ $c }}.items.{{ $i }}.image"
                            wire:key="feature-{{ $item['id'] ?? $i }}-image"
                            name="feature-{{ $item['id'] ?? $i }}-image"
                            type="image"
                            :locale="$locale"
                            :multiple="false"
                            label="{{ __('Image or icon') }}" />

                        <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.title" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Title') }}" />
                        <x-forms.texteditor-translated name="{{ $c }}.items.{{ $i }}.body" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Description') }}" toolbar="bold italic | align | link" />

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
                                        <flux:input wire:model.lazy="{{ $c }}.items.{{ $i }}.cta.link.value" type="url" label="{{ __('URL') }}" placeholder="https://example.com" />
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
            <flux:button size="sm" icon="plus" wire:click="addFeatureItem('{{ $block['id'] }}')">{{ __('Add card') }}</flux:button>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
            @foreach ([2, 3, 4] as $columnOption)
                <flux:radio value="{{ $columnOption }}" label="{{ $columnOption }}" />
            @endforeach
        </flux:radio.group>

        <flux:select wire:model.live="{{ $c }}.imageHeight" variant="listbox" label="{{ __('Image height') }}">
            <flux:select.option value="icon">{{ __('Icon') }}</flux:select.option>
            <flux:select.option value="small">{{ __('Small') }}</flux:select.option>
            <flux:select.option value="medium">{{ __('Medium') }}</flux:select.option>
            <flux:select.option value="large">{{ __('Large') }}</flux:select.option>
            <flux:select.option value="xl">{{ __('Extra large') }}</flux:select.option>
        </flux:select>
    </div>

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.live="{{ $c }}.imageRounded" label="{{ __('Round the image corners') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.cardStyle" label="{{ __('Show cards with a background') }}" align="left" />
    </div>

    <div x-show="{{ $b }}?.cardStyle" x-cloak class="grid md:grid-cols-2 gap-4">
        <flux:color-picker wire:model="{{ $c }}.cardBg" clearable label="{{ __('Card background') }}" placeholder="{{ __('Theme') }}" />
        <flux:color-picker wire:model="{{ $c }}.cardText" clearable label="{{ __('Card text') }}" placeholder="{{ __('Theme') }}" />
    </div>
</div>
