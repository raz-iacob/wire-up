@props(['logo' => null, 'logoDark' => null, 'brand' => '', 'size' => 'md', 'height' => null])

@php
    $sizeClass = match ($size) {
        'sm' => 'h-6',
        'lg' => 'h-14',
        default => 'h-8',
    };
    $imgClass = ($height ? '' : $sizeClass).' w-auto object-contain';
    $imgStyle = $height ? 'height:'.$height.'px' : null;
    $light = $logo ?: $logoDark;
    $dark = $logoDark ?: $logo;
@endphp

<a href="/" wire:navigate {{ $attributes->merge(['class' => 'inline-flex items-center']) }} aria-label="{{ $brand }}">
    @if ($light)
        <img
            src="{{ $light }}"
            alt="{{ $brand }}"
            @class([$imgClass, 'dark:hidden' => $light !== $dark])
            @if ($imgStyle) style="{{ $imgStyle }}" @endif
        />
        @if ($light !== $dark)
            <img
                src="{{ $dark }}"
                alt="{{ $brand }}"
                class="{{ $imgClass }} hidden dark:block"
                @if ($imgStyle) style="{{ $imgStyle }}" @endif
            />
        @endif
    @else
        <span
            @class([
                'text-sm' => $size === 'sm',
                'text-base' => $size === 'md',
                'text-xl' => $size === 'lg',
                'font-bold',
                'tracking-tight',
            ])
        >{{ $brand }}</span>
    @endif
</a>
