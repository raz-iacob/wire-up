@props(['block', 'locale', 'multiLocale' => false, 'index'])

@php
    $c = "blocks.{$index}.content";
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.body" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Text') }}" />

    <div class="grid md:grid-cols-2 gap-4">
        <flux:radio.group wire:model.lazy="{{ $c }}.width" variant="segmented" label="{{ __('Width') }}">
            <flux:radio value="normal" label="{{ __('Normal') }}" />
            <flux:radio value="narrow" label="{{ __('Narrow') }}" />
        </flux:radio.group>

        <flux:radio.group wire:model.lazy="{{ $c }}.align" variant="segmented" label="{{ __('Alignment') }}">
            <flux:radio value="left" icon="bars-3-bottom-left" label="{{ __('Left') }}" />
            <flux:radio value="center" icon="bars-3" label="{{ __('Center') }}" />
        </flux:radio.group>
    </div>

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
