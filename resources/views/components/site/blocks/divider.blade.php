@props(['block'])

@php
    $size = $block->content['size'] ?? 'medium';
    $height = match ($size) {
        'thin' => '1px',
        'thick' => '3px',
        default => '2px',
    };
@endphp

<div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
    <hr class="border-0" style="height:{{ $height }};background-color:var(--wire-card-border)" />
</div>
