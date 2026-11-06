@props(['connected' => false, 'test' => null])

<div class="flex items-center justify-between gap-4">
    @if ($connected)
        <flux:button variant="subtle" wire:click="disconnect">{{ __('Disconnect') }}</flux:button>
    @else
        <span></span>
    @endif
    @if ($test !== null)
        <div class="flex items-center gap-2">
            <flux:button wire:click="test" :tooltip="$test">{{ __('Test') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check">{{ __('Save') }}</flux:button>
        </div>
    @else
        <flux:button type="submit" variant="primary" icon="check">{{ __('Save') }}</flux:button>
    @endif
</div>
