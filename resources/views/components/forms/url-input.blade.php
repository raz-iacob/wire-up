@props(['placeholder' => 'https://example.com'])

<div x-data="{ url: '' }" class="contents">
    <flux:input
        {{ $attributes }}
        :placeholder="$placeholder"
        x-init="url = $el.value"
        x-on:input="url = $el.value"
        x-on:change="url = $el.value"
    >
        <x-slot name="iconTrailing">
            <flux:button
                size="sm"
                variant="ghost"
                icon="arrow-up-right"
                class="-mr-1"
                type="button"
                x-bind:disabled="! /^https?:\/\//i.test(url.trim())"
                aria-label="{{ __('Open in a new tab') }}"
                x-on:click.prevent="window.open(url.trim(), '_blank', 'noopener')"
            />
        </x-slot>
    </flux:input>
</div>
