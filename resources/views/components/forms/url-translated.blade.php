@props(['name' => 'slugs', 'locale' => app()->getLocale(), 'multiLocale' => false, 'label' => null, 'required' => false, 'readonly' => false, 'note' => '', 'basePath' => ''])

@php
    $localeSegment = $locale !== config()->string('app.locale') ? $locale.'/' : '';
    $pathPrefix = $localeSegment.($basePath !== '' ? $basePath.'/' : '');
@endphp

<flux:field wire:key="{{ $name }}-{{ $locale }}">
    <div class="flex items-center gap-3">
        @if($label)
        <flux:label>{{ $label }}</flux:label>
        @endif

        @if($multiLocale)
        <flux:tooltip content="{{ __('Change language') }}">
            <flux:badge size="sm" class="text-xs py-0.5!" as="button" inset="top bottom" x-on:click="$wire.dispatch('change-locale')">{{ strtoupper($locale) }}</flux:badge>
        </flux:tooltip>
        @endif

        @if($note)
        <flux:text class="ml-auto text-sm">{{ $note }}</flux:text>
        @endif
    </div>
    <flux:input.group>
        <flux:input.group.prefix>{{ config('app.url') }}/{{ $pathPrefix }}</flux:input.group.prefix>
        <flux:input wire:model.lazy="{{ $name }}.{{ $locale }}" :required="$required" :readonly="$readonly" x-on:input="$el.value = $el.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/-{2,}/g, '-')">
            <x-slot name="iconTrailing">
                <flux:button size="sm" variant="ghost" icon="arrow-up-right" class="-mr-1" type="button"
                    x-on:click.prevent="window.open('{{ config('app.url') }}/{{ $pathPrefix }}' + $wire.{{ $name }}.{{ $locale }}, '_blank')"
                />
            </x-slot>
        </flux:input>
    </flux:input.group>
    <flux:error name="{{ $name }}.{{ $locale }}" />
</flux:field>