@props(['items' => [], 'size' => 'md', 'hover' => 'opacity'])

@php
    $items = array_values(array_filter($items, fn (array $item): bool => ($item['type'] ?? 'link') !== 'heading'));
@endphp

@if (! empty($items))
    <nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-6']) }}>
        @foreach ($items as $item)
            @if (($item['appearance'] ?? '') === 'icon' && ($item['icon_svg'] ?? '') !== '')
                <a
                    href="{{ $item['url'] }}"
                    aria-label="{{ $item['label'] }}"
                    title="{{ $item['label'] }}"
                    @if ($item['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif
                    @class([
                        'inline-flex items-center transition',
                        '[&>svg]:size-5' => $size === 'sm',
                        '[&>svg]:size-6' => $size === 'md',
                        '[&>svg]:size-7' => $size === 'lg',
                        'hover:opacity-70' => $hover === 'opacity',
                        'hover:scale-105' => $hover === 'scale' || $hover === 'underline',
                    ])
                >{!! $item['icon_svg'] !!}</a>
            @else
                <a
                    href="{{ $item['url'] }}"
                    @if ($item['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif
                    @class([
                        'font-medium transition',
                        'rounded-(--wire-radius) px-4 py-2 bg-(--wire-primary-bg) text-(--wire-primary-text)' => $item['appearance'] === 'button',
                        'text-sm' => $size === 'sm',
                        'text-base' => $size === 'md',
                        'text-lg' => $size === 'lg',
                        'hover:opacity-70' => $hover === 'opacity',
                        'hover:underline' => $hover === 'underline',
                        'hover:scale-105' => $hover === 'scale',
                    ])
                >{{ $item['label'] }}</a>
            @endif
        @endforeach
    </nav>
@endif
