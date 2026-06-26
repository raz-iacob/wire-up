@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks[".json_encode((string) $index)."].content";
    $linkType = data_get($block, 'content.imageLink.link.type', 'url');
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.intro" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" />

    <livewire:admin.media-selector
        wire:model="{{ $c }}.image"
        wire:key="block-{{ $block['id'] }}-image"
        name="block-{{ $block['id'] }}-image"
        type="image"
        :locale="$locale"
        :multiple="false"
        :crops="['desktop' => ['label' => __('Desktop'), 'q' => 80, 'fm' => 'jpg'], 'mobile' => ['label' => __('Mobile'), 'w' => 1080, 'h' => 1350, 'q' => 80, 'fm' => 'jpg']]"
        label="{{ __('Image') }}" />

    <flux:radio.group wire:model.live="{{ $c }}.width" variant="segmented" label="{{ __('Width') }}">
        <flux:radio value="normal" label="{{ __('Normal') }}" />
        <flux:radio value="full" label="{{ __('Full-bleed') }}" />
    </flux:radio.group>

    <div class="flex flex-col gap-4">
        <flux:select wire:model.live="{{ $c }}.imageLink.link.type" variant="listbox" label="{{ __('Link (optional)') }}">
            <flux:select.option value="url">{{ __('External URL') }}</flux:select.option>
            <flux:select.option value="page">{{ __('A page') }}</flux:select.option>
            <flux:select.option value="anchor">{{ __('Section on this page') }}</flux:select.option>
        </flux:select>

        @if ($linkType === 'page')
            <flux:select wire:model="{{ $c }}.imageLink.link.value" variant="listbox" searchable placeholder="{{ __('Choose a page') }}" label="{{ __('Page') }}">
                @foreach ($pageOptions as $pageId => $pageTitle)
                    <flux:select.option value="{{ $pageId }}">{{ $pageTitle }}</flux:select.option>
                @endforeach
            </flux:select>
        @elseif ($linkType === 'anchor')
            <flux:input wire:model.lazy="{{ $c }}.imageLink.link.value" label="{{ __('Section anchor') }}" placeholder="#contact" />
        @else
            <flux:input wire:model.lazy="{{ $c }}.imageLink.link.value" label="{{ __('URL') }}" placeholder="https://example.com" />
            <flux:switch wire:model.lazy="{{ $c }}.imageLink.link.newTab" label="{{ __('Open in a new tab') }}" align="left" />
        @endif
    </div>

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
