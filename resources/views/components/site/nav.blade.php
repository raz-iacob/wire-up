@props(['items' => [], 'size' => 'md', 'hover' => 'opacity'])

@if (! empty($items))
    <nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-6']) }}>
        @foreach ($items as $item)
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
        @endforeach
    </nav>
@endif
