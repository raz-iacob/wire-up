<div class="flex items-center gap-3">
    <flux:label>{{ $label }}</flux:label>

    @if(($translatable ?? false) && ($multiLocale ?? false))
    <flux:tooltip content="{{ __('Change language') }}">
        <flux:badge size="sm" class="text-xs py-0.5!" as="button" inset="top bottom" x-on:click="$wire.dispatch('change-locale')">{{ strtoupper($locale) }}</flux:badge>
    </flux:tooltip>
    @endif
</div>
