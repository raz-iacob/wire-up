@props([
    'label',
])

<flux:badge class="shrink-0" {{ $attributes->whereStartsWith('wire:key') }}>
    {{ $label }}
    <flux:badge.close {{ $attributes->whereStartsWith('wire:click') }} :aria-label="__('Remove filter')" />
</flux:badge>
