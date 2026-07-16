@props(['logo' => null, 'logoDark' => null, 'brand' => '', 'size' => 'md'])

@php
    $sizeClass = match ($size) {
        'sm' => 'h-6',
        'lg' => 'h-14',
        default => 'h-8',
    };
    $imgClass = $sizeClass.' w-auto object-contain';
    $light = $logo ?: $logoDark;
    $dark = $logoDark ?: $logo;
@endphp

<a href="/" wire:navigate {{ $attributes->merge(['class' => 'inline-flex items-center']) }} aria-label="{{ $brand }}">
    @if ($light)
        <img src="{{ $light }}" alt="{{ $brand }}" @class([$imgClass, 'dark:hidden' => $light !== $dark]) />
        @if ($light !== $dark)
            <img src="{{ $dark }}" alt="{{ $brand }}" class="{{ $imgClass }} hidden dark:block" />
        @endif
    @else
        <span @class([
            'text-sm' => $size === 'sm',
            'text-base' => $size === 'md',
            'text-xl' => $size === 'lg',
            'font-bold',
            'tracking-tight'
        ])>{{ $brand }}</span>
    @endif
</a>
