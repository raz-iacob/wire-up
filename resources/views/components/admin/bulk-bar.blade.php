<div x-cloak x-show="$wire.selected.length > 0" {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
    <flux:text variant="strong" class="me-1 shrink-0 tabular-nums">
        <span x-text="$wire.selected.length"></span> {{ __('selected') }}
    </flux:text>
    {{ $slot }}
    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearSelection">{{ __('Clear') }}</flux:button>
</div>
