@props([
    'from',
    'discardUrl',
])

<flux:callout icon="clock" color="amber" :heading="__('Showing the version from :time', ['time' => $from])">
    <flux:callout.text>
        {{ __('Click Update to keep it, or discard it to go back to what is saved.') }}</flux:callout.text>

    <x-slot name="actions">
        <flux:button size="sm" :href="$discardUrl">{{ __('Discard') }}</flux:button>
    </x-slot>
</flux:callout>
