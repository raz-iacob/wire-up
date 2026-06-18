@props(['block', 'locale', 'multiLocale' => false, 'index'])

<div class="flex flex-col gap-6">
    <flux:radio.group wire:model.lazy="blocks.{{ $index }}.content.size" label="{{ __('Spacer size') }}" variant="segmented">
        <flux:radio value="small" label="{{ __('Small') }}" />
        <flux:radio value="medium" label="{{ __('Medium') }}" />
        <flux:radio value="large" label="{{ __('Large') }}" />
    </flux:radio.group>
</div>
