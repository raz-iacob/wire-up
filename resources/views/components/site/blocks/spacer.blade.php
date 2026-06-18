@props(['block'])

@php
    $size = $block->content['size'] ?? 'medium';
@endphp

<div aria-hidden="true" @class([
    'h-8' => $size === 'small',
    'h-16' => $size === 'medium',
    'h-32' => $size === 'large',
])></div>
