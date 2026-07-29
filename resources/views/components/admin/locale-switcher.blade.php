@props(['locale'])

<flux:tooltip content="{{ __('Change language') }}">
    <flux:badge
        size="sm"
        class="py-0.5! text-xs"
        as="button"
        inset="top bottom"
        x-on:click="$wire.dispatch('change-locale')"
    >{{ strtoupper($locale) }}</flux:badge>
</flux:tooltip>
