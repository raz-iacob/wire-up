@props(['locale'])

<flux:tooltip content="{{ __('Change language') }}">
    <flux:badge size="sm" class="text-xs py-0.5!" as="button" inset="top bottom" x-on:click="$wire.dispatch('change-locale')">{{ strtoupper($locale) }}</flux:badge>
</flux:tooltip>
