@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" toolbar="bold italic underline" />

    <livewire:admin.media-selector
        wire:model="{{ $c }}.media"
        wire:key="block-{{ $block['id'] }}-media"
        name="block-{{ $block['id'] }}-media"
        type="image,video"
        :locale="$locale"
        :multi-locale="$multiLocale"
        :multiple="true"
        :with-caption="true"
        :label="__('Images & videos')" />

    <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
        @foreach ([2, 3, 4, 5, 6] as $columnOption)
            <flux:radio value="{{ $columnOption }}" label="{{ $columnOption }}" />
        @endforeach
    </flux:radio.group>

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.lazy="{{ $c }}.lightbox" label="{{ __('Open media in a lightbox on click') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
    </div>
</div>
