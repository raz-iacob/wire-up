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
        <flux:label>{{ __('Testimonials') }}</flux:label>

        <div wire:sort="reorderTestimonialItems" wire:sort:group="testimonials-{{ $block['id'] }}" class="flex flex-col gap-3">
            @foreach ($items as $i => $item)
                @php($itemTitle = \Illuminate\Support\Str::of((string) data_get($item, "author.{$locale}"))->squish()->limit(50)->value())
                <flux:card
                    size="sm"
                    class="p-0! overflow-hidden"
                    wire:key="testimonial-item-{{ $item['id'] ?? $i }}"
                    wire:sort:item="{{ $item['id'] ?? $i }}"
                    x-data="{ open: {{ $itemTitle === '' ? 'true' : 'false' }} }"
                >
                    <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                            <flux:icon name="bars-3" variant="mini" />
                        </div>

                        <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                            <flux:heading size="sm" class="truncate">{{ $itemTitle !== '' ? $itemTitle : __('Testimonial :number', ['number' => $i + 1]) }}</flux:heading>
                        </button>

                        <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                            <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                            </flux:button>
                            <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removeTestimonialItem('{{ $block['id'] }}', {{ $i }})" :tooltip="__('Remove testimonial')" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 p-4" x-show="open" x-collapse x-cloak>
                        <x-forms.texteditor-translated name="{{ $c }}.items.{{ $i }}.quote" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Quote') }}" toolbar="bold italic | align | link" />

                        <div class="grid md:grid-cols-2 gap-4">
                            <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.author" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Author') }}" />
                            <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.role" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Role / company') }}" />
                        </div>

                        <livewire:admin.blocks.item-media
                            :block-id="$block['id']"
                            item-id="{{ $item['id'] ?? $i }}"
                            field="avatar"
                            :value="data_get($item, 'avatar')"
                            media-type="image"
                            :multiple="false"
                            :crops="['default' => ['label' => __('Square'), 'w' => 400, 'h' => 400]]"
                            :locale="$locale"
                            :multi-locale="$multiLocale"
                            label="{{ __('Avatar') }}"
                            wire:key="testimonial-avatar-{{ $block['id'] }}-{{ $item['id'] ?? $i }}" />

                        <flux:select wire:model="{{ $c }}.items.{{ $i }}.rating" variant="listbox" label="{{ __('Rating') }}">
                            <flux:select.option value="0">{{ __('No rating') }}</flux:select.option>
                            @for ($star = 1; $star <= 5; $star++)
                                <flux:select.option value="{{ $star }}">{{ trans_choice('{1} :count star|[2,*] :count stars', $star, ['count' => $star]) }}</flux:select.option>
                            @endfor
                        </flux:select>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div>
            <flux:button size="sm" icon="plus" wire:click="addTestimonialItem('{{ $block['id'] }}')">{{ __('Add testimonial') }}</flux:button>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <flux:select wire:model.live="{{ $c }}.layout" variant="listbox" label="{{ __('Layout') }}">
            <flux:select.option value="grid">{{ __('Grid') }}</flux:select.option>
            <flux:select.option value="carousel">{{ __('Carousel') }}</flux:select.option>
            <flux:select.option value="single">{{ __('Single (featured)') }}</flux:select.option>
            <flux:select.option value="split">{{ __('Split') }}</flux:select.option>
        </flux:select>

        <div x-show="{{ $b }}?.layout === 'grid'" x-cloak>
            <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
                <flux:radio value="2" label="{{ __('Two') }}" />
                <flux:radio value="3" label="{{ __('Three') }}" />
            </flux:radio.group>
        </div>
    </div>

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.amberStars" label="{{ __('Use gold star ratings') }}" align="left" />
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <flux:color-picker wire:model="{{ $c }}.cardBg" clearable label="{{ __('Card background') }}" placeholder="{{ __('Theme') }}" />
        <flux:color-picker wire:model="{{ $c }}.cardText" clearable label="{{ __('Card text') }}" placeholder="{{ __('Theme') }}" />
    </div>
</div>
