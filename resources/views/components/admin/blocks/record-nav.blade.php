@props(['block', 'locale', 'multiLocale' => false, 'index'])

@php
    $c = "blocks.{$index}.content";
@endphp

<div class="flex flex-col gap-6">
    <flux:text variant="subtle">{{ __('Only shows on a record, linking to the ones published before and after it.') }}</flux:text>

    <div class="grid gap-4 md:grid-cols-2">
        <x-forms.input-translated
            name="{{ $c }}.previousLabel"
            :locale="$locale"
            :multi-locale="$multiLocale"
            label="{{ __('Previous label') }}"
            note="{{ __('Defaults to “Previous”') }}"
        />
        <x-forms.input-translated
            name="{{ $c }}.nextLabel"
            :locale="$locale"
            :multi-locale="$multiLocale"
            label="{{ __('Next label') }}"
            note="{{ __('Defaults to “Next”') }}"
        />
    </div>

    <flux:switch wire:model.live="{{ $c }}.showTitles" label="{{ __('Show the record titles') }}" align="left" />
    <flux:switch
        wire:model.live="{{ $c }}.sameCategory"
        label="{{ __('Stay within the same category') }}"
        align="left"
    />
    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
