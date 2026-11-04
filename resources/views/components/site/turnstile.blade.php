@props(['error' => 'turnstile'])

@php
    $turnstile = resolve(\App\Services\Turnstile::class);
@endphp

@if ($turnstile->enabled())
    <div {{ $attributes->class('space-y-2') }}>
        <div
            wire:ignore
            x-data="turnstile(@js($turnstile->siteKey()))"
            x-on:turnstile-reset.window="reset()"
            data-turnstile
        ></div>

        @if ($error !== 'form')
            @error($error)
                <p class="text-sm font-medium text-red-600">{{ $message }}</p>
            @enderror
        @endif
    </div>
@endif
