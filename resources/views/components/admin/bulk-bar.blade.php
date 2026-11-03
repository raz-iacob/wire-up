<div x-cloak x-show="$wire.selected.length > 0" {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
    <flux:dropdown position="bottom" align="start">
        <flux:button size="sm" icon:trailing="chevron-down" class="tabular-nums">
            <span x-text="$wire.selected.length"></span> {{ __('selected') }}
        </flux:button>

        <flux:menu> {{ $slot }} </flux:menu>
    </flux:dropdown>
    <flux:button size="sm" variant="ghost" wire:click="clearSelection">{{ __('Clear') }}</flux:button>
</div>
