@props(['name' => 'title', 'locale' => app()->getLocale(), 'label' => '', 'note' => '', 'required' => false])

<flux:field wire:key="{{ $name }}-{{ $locale }}">
    <div class="flex items-center gap-3 mb-2">
        @if(!empty($label))
        <flux:label>{{ $label }}</flux:label>
        @endif

        @if(app('locales')->count() > 1)
        <flux:tooltip content="{{ __('Change language') }}">
            <flux:badge size="sm" class="text-xs py-0.5!" as="button" x-on:click="$wire.dispatch('change-language')">{{ strtoupper($locale) }}</flux:badge>
        </flux:tooltip>
        @endif

        @if($note)
        <flux:text class="ml-auto text-sm">{{ $note }}</flux:text>
        @endif
    </div>
    <flux:input wire:model.lazy="{{ $name }}.{{ $locale }}" :required="$required" />
    <flux:error name="{{ $name }}.{{ $locale }}" />
</flux:field>