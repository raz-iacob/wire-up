@props(['block', 'locale', 'multiLocale' => false, 'index'])

<div class="flex flex-col gap-6">
    <flux:radio.group wire:model.lazy="blocks.{{ $index }}.content.size" label="{{ __('Thickness') }}" variant="segmented">
        <flux:radio value="thin" label="{{ __('Thin') }}" />
        <flux:radio value="medium" label="{{ __('Medium') }}" />
        <flux:radio value="thick" label="{{ __('Thick') }}" />
    </flux:radio.group>
</div>
