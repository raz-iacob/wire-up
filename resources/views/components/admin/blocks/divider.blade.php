@props(['block', 'locale', 'multiLocale' => false, 'index'])

<div class="flex flex-col gap-6">
    <flux:radio.group
        wire:model.lazy="blocks.{{ $index }}.content.size"
        label="{{ __('Thickness') }}"
        variant="segmented"
    >
        <flux:radio value="thin" label="{{ __('Thin') }}" />
        <flux:radio value="medium" label="{{ __('Medium') }}" />
        <flux:radio value="thick" label="{{ __('Thick') }}" />
    </flux:radio.group>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:select wire:model.lazy="blocks.{{ $index }}.content.style" label="{{ __('Line style') }}">
            <flux:select.option value="solid">{{ __('Solid') }}</flux:select.option>
            <flux:select.option value="fade-edges">{{ __('Fade at both edges') }}</flux:select.option>
            <flux:select.option value="fade-right">{{ __('Fade to the right') }}</flux:select.option>
            <flux:select.option value="fade-left">{{ __('Fade to the left') }}</flux:select.option>
            <flux:select.option value="dotted">{{ __('Dotted') }}</flux:select.option>
            <flux:select.option value="dashed">{{ __('Dashed') }}</flux:select.option>
        </flux:select>

        <flux:color-picker
            wire:model="blocks.{{ $index }}.content.color"
            clearable
            label="{{ __('Line color') }}"
            placeholder="{{ __('Theme') }}"
        />
    </div>

    <flux:radio.group wire:model.lazy="blocks.{{ $index }}.content.width" label="{{ __('Width') }}" variant="segmented">
        <flux:radio value="normal" label="{{ __('Content width') }}" />
        <flux:radio value="full" label="{{ __('Full width') }}" />
    </flux:radio.group>
</div>
