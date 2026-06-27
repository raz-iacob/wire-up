@props(['block'])

@php
    $size = $block->content['size'] ?? 'medium';
    $heightClass = match ($size) {
        'thin' => 'h-px',
        'thick' => 'h-[3px]',
        default => 'h-0.5',
    };
@endphp

<div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
    <hr class="border-0 {{ $heightClass }} bg-(--wire-divider)" />
</div>
