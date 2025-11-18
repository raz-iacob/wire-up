@props(['name' => 'slugs', 'locale' => app()->getLocale(), 'label' => '', 'required' => false])

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
    </div>
    <flux:input.group>
        <flux:input.group.prefix>{{ config('app.url') }}/{{ $locale !== config()->string('app.locale') ? $locale .'/' : '' }}</flux:input.group.prefix>
        <flux:input wire:model.lazy="{{ $name }}.{{ $locale }}" :required="$required" x-on:input="$el.value = $el.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/-{2,}/g, '-')">
            <x-slot name="iconTrailing">
                <flux:button size="sm" variant="ghost" icon="arrow-up-right" class="-mr-1" type="button"
                    x-on:click.prevent="window.open('{{ config('app.url') }}/{{ $locale !== config()->string('app.locale') ? $locale .'/' : '' }}' + $wire.{{ $name }}.{{ $locale }}, '_blank')"
                />
            </x-slot>
        </flux:input>
    </flux:input.group>
    <flux:error name="{{ $name }}.{{ $locale }}" />
</flux:field>