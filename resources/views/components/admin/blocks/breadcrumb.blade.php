@props(['block', 'locale', 'multiLocale' => false, 'index'])

@php
    $c = "blocks.{$index}.content";
    $b = '$wire.blocks['.json_encode((string) $index).'].content';
@endphp

<div class="flex flex-col gap-6">
    <flux:text variant="subtle">{{ __('The trail is built automatically. Nothing to fill in.') }}</flux:text>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:radio.group wire:model.lazy="{{ $c }}.align" variant="segmented" label="{{ __('Alignment') }}">
            <flux:radio value="left" icon="bars-3-bottom-left" label="{{ __('Left') }}" />
            <flux:radio value="center" icon="bars-3" label="{{ __('Center') }}" />
        </flux:radio.group>

        <flux:radio.group wire:model.lazy="{{ $c }}.separator" variant="segmented" label="{{ __('Separator') }}">
            <flux:radio value="/" label="/" />
            <flux:radio value="›" label="›" />
            <flux:radio value="•" label="•" />
        </flux:radio.group>
    </div>

    <flux:switch wire:model.live="{{ $c }}.showHome" label="{{ __('Show home link') }}" align="left" />

    <div x-show="{{ $b }}?.showHome" x-cloak>
        <x-forms.input-translated
            name="{{ $c }}.homeLabel"
            :locale="$locale"
            :multi-locale="$multiLocale"
            label="{{ __('Home label') }}"
            note="{{ __('Defaults to “Home”') }}"
        />
    </div>

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
