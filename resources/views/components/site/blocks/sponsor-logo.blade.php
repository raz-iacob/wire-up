@props(['item', 'grayscale' => false, 'showName' => false, 'size' => 'md'])

@php
    $height = match ($size) {
        'lg' => 'h-16 md:h-20',
        'sm' => 'h-10 md:h-12',
        default => 'h-12 md:h-16',
    };
    $imgClass = "{$height} w-auto max-w-full object-contain".($grayscale ? ' opacity-90 grayscale transition duration-300 hover:opacity-100 hover:grayscale-0' : '');
@endphp

<figure {{ $attributes->class('m-0 flex flex-col items-center gap-3') }}>
    @if ($item['link'] !== '')
        <a href="{{ $item['link'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center" @if ($item['name'] !== '') title="{{ $item['name'] }}" @endif>
            <img src="{{ $item['logo'] }}" alt="{{ $item['alt'] }}" loading="lazy" class="{{ $imgClass }}" />
        </a>
    @else
        <img src="{{ $item['logo'] }}" alt="{{ $item['alt'] }}" loading="lazy" class="{{ $imgClass }}" />
    @endif

    @if ($showName && $item['name'] !== '')
        <figcaption class="text-center text-sm opacity-80">{{ $item['name'] }}</figcaption>
    @endif
</figure>
