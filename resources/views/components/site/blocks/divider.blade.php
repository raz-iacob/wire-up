@props(['block'])

@php
    $size = $block->plain('size', 'medium');
    $thickness = match ($size) {
        'thin' => '1px',
        'thick' => '3px',
        default => '2px',
    };

    $style = $block->plain('style', 'solid');
    $style = in_array($style, ['solid', 'fade-edges', 'fade-right', 'fade-left', 'dotted', 'dashed'], true)
        ? $style
        : 'solid';

    $color = $block->plain('color');
    $color = preg_match('/^[#a-zA-Z0-9(),.%\s-]+$/', $color) === 1 ? $color : '';
    $color = $color !== '' ? $color : 'var(--wire-divider)';

    $full = $block->plain('width', 'normal') === 'full';

    $line = match ($style) {
        'dotted', 'dashed' => "height:0;border:0;border-top:{$thickness} {$style} {$color}",
        'fade-edges' => "border:0;height:{$thickness};background-image:linear-gradient(to right, transparent, {$color}, transparent)",
        'fade-right' => "border:0;height:{$thickness};background-image:linear-gradient(to right, {$color}, transparent)",
        'fade-left' => "border:0;height:{$thickness};background-image:linear-gradient(to right, transparent, {$color})",
        default => "border:0;height:{$thickness};background-color:{$color}",
    };
@endphp

<div @class(['mx-auto max-w-(--wire-container) px-(--wire-gutter)' => ! $full])>
    <hr class="m-0 w-full" style="{{ $line }}" />
</div>
