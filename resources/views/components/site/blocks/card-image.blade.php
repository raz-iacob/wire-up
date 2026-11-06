@props(['src', 'alt' => '', 'shape' => 'landscape', 'fit' => 'cover', 'srcset' => null, 'sizes' => '100vw'])

@php
    $shapeClass = match ($shape) {
        'none' => '',
        'square' => 'aspect-square',
        'portrait' => 'aspect-4/5',
        default => 'aspect-4/3',
    };
    $contain = $fit === 'contain';
@endphp

<img
    src="{{ $src }}"
    @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
    alt="{{ $alt }}"
    loading="lazy"
    {{
        $attributes->class([
            $shapeClass,
            'object-cover' => ! $contain,
            'bg-current/5 object-contain p-3' => $contain,
        ])
    }}
/>
