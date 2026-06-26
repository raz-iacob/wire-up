@props(['block', 'locale', 'multiLocale' => false, 'index'])

@php
    $c = "blocks.{$index}.content";
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.intro" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" />

    <livewire:admin.media-selector
        wire:model="{{ $c }}.audio"
        wire:key="block-{{ $block['id'] }}-audio"
        name="block-{{ $block['id'] }}-audio"
        type="audio"
        :locale="$locale"
        :multi-locale="$multiLocale"
        :multiple="false"
        :label="__('Audio file')" />

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
