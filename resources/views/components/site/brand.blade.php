@props(['logo' => null, 'brand' => '', 'size' => 'md'])

<a href="/" wire:navigate {{ $attributes->merge(['class' => 'inline-flex items-center']) }} aria-label="{{ $brand }}">
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ $brand }}" @class([
            'h-6' => $size === 'sm',
            'h-8' => $size === 'md',
            'h-14' => $size === 'lg',
            'w-auto',
            'object-contain'
        ]) />
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
