@props(['block', 'locale', 'multiLocale' => false, 'index'])

@php
    $c = "blocks.{$index}.content";
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.intro" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" />

    <livewire:admin.media-selector
        wire:model="{{ $c }}.files"
        wire:key="block-{{ $block['id'] }}-files"
        name="block-{{ $block['id'] }}-files"
        type="document"
        :locale="$locale"
        :multi-locale="$multiLocale"
        :multiple="true"
        :with-caption="true"
        :label="__('Files')" />

    <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
        @foreach ([1, 2, 3, 4, 5] as $columnOption)
            <flux:radio value="{{ $columnOption }}" label="{{ $columnOption }}" />
        @endforeach
    </flux:radio.group>

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
