@props(['name' => 'title', 'lang' => 'en', 'label' => 'Description', 'required' => false, 'prefix' => 'form.translated'])

<flux:field wire:key="{{ $name }}-{{ $lang }}">
    <div class="flex items-center gap-3 mb-2">
        <flux:label>{{ $label }} {{ $required ? '*' : '' }}</flux:label>
        <flux:tooltip content="Change language">
            <flux:badge size="sm" as="button" x-on:click="$wire.dispatch('change-language')">{{ strtoupper($lang) }}</flux:badge>
        </flux:tooltip>
    </div>
    <flux:editor wire:model.lazy="{{ $prefix }}.{{ $name }}.{{ $lang }}" :required="$required" toolbar="bold italic underline | align ~ undo redo" class="**:data-[slot=content]:min-h-[100px]!" />
    <flux:error name="{{ $prefix }}.{{ $name }}.{{ $lang }}" />
</flux:field>