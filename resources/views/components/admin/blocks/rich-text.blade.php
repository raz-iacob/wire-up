@props(['block', 'locale', 'multiLocale' => false, 'index'])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks['".addslashes((string) $index)."'].content";
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated
        name="{{ $c }}.heading"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Heading') }}"
    />
    <x-forms.texteditor-translated
        name="{{ $c }}.body"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Text') }}"
    />

    <div class="grid gap-4 md:grid-cols-2">
        <flux:radio.group wire:model.lazy="{{ $c }}.width" variant="segmented" label="{{ __('Width') }}">
            <flux:radio value="normal" label="{{ __('Normal') }}" />
            <flux:radio value="narrow" label="{{ __('Narrow') }}" />
        </flux:radio.group>

        <flux:radio.group
            wire:model.lazy="{{ $c }}.align"
            variant="segmented"
            label="{{ __('Alignment') }}"
            x-show="{{ $b }}?.width === 'narrow'"
            x-cloak
        >
            <flux:radio value="left" icon="bars-3-bottom-left" label="{{ __('Left') }}" />
            <flux:radio value="center" icon="bars-3" label="{{ __('Center') }}" />
            <flux:radio value="right" icon="bars-3-bottom-right" label="{{ __('Right') }}" />
        </flux:radio.group>
    </div>

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
