@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks[".json_encode((string) $index)."].content";
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" toolbar="bold italic underline | align" />

    <flux:input
        wire:model.lazy="{{ $c }}.map"
        label="{{ __('Map address or embed URL') }}"
        placeholder="{{ __('123 Main St, Springfield') }}"
        description="{{ __('Enter an address, or paste a Google Maps embed URL.') }}" />

    <x-forms.input-translated name="{{ $c }}.name" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Name') }}" />
    <x-forms.textarea-translated name="{{ $c }}.address" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Address') }}" />

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:input wire:model.lazy="{{ $c }}.phone" type="tel" label="{{ __('Phone') }}" placeholder="+1 555 123 4567" />
        <flux:input wire:model.lazy="{{ $c }}.email" type="email" label="{{ __('Email') }}" placeholder="hello@example.com" />
    </div>

    <x-forms.texteditor-translated name="{{ $c }}.hours" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Opening hours') }}" toolbar="bold italic | bullet ordered" />

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
        <flux:switch wire:model.lazy="{{ $c }}.reverseLayout" label="{{ __('Display map on the right') }}" align="left" />
    </div>

    <flux:switch wire:model.live="{{ $c }}.directions.enabled" label="{{ __('Show a directions button') }}" align="left" />

    <div x-show="{{ $b }}?.directions?.enabled" class="grid md:grid-cols-2 gap-4">
        <x-forms.input-translated name="{{ $c }}.directions.text" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Button text') }}" />

        <div class="grid grid-cols-2 gap-4">
            <flux:color-picker wire:model="{{ $c }}.directions.bg" clearable label="{{ __('Button color') }}" placeholder="{{ __('Theme') }}" />
            <flux:color-picker wire:model="{{ $c }}.directions.textColor" clearable label="{{ __('Text color') }}" placeholder="{{ __('Theme') }}" />
        </div>
    </div>
</div>
