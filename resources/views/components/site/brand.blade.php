@props(['logo' => null, 'brand' => ''])

<a href="/" wire:navigate {{ $attributes->merge(['class' => 'inline-flex items-center']) }} aria-label="{{ $brand }}">
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ $brand }}" class="h-8 w-auto object-contain" />
    @else
        <span class="text-lg font-bold tracking-tight">{{ $brand }}</span>
    @endif
</a>
