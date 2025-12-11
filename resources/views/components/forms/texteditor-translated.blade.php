@props(['name' => 'title', 'locale' => 'en', 'label' => null, 'required' => false, 'multiLocale' => false])

<flux:field wire:key="{{ $name }}-{{ $locale }}">
    <div class="flex items-center gap-3 mb-2">
        @if($label)
        <flux:label>{{ $label }}</flux:label>
        @endif
        @if($multiLocale)
        <flux:tooltip content="{{ __('Change language') }}">
            <flux:badge size="sm" class="text-xs py-0.5!" as="button" x-on:click="$wire.dispatch('change-locale')">{{ strtoupper($locale) }}</flux:badge>
        </flux:tooltip>
        @endif
    </div>
    <flux:editor wire:model.lazy="{{ $name }}.{{ $locale }}" :required="$required" toolbar="bold italic underline | align ~ undo redo" class="**:data-[slot=content]:min-h-[100px]!" />
    <flux:error name="{{ $name }}.{{ $locale }}" />
</flux:field>