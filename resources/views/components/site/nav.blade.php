@props(['items' => []])

@if (! empty($items))
    <nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-6']) }}>
        @foreach ($items as $item)
            <a
                href="{{ $item['url'] }}"
                @if ($item['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif
                @class([
                    'text-sm font-medium transition-opacity',
                    'rounded-md px-4 py-2 hover:opacity-90 bg-(--wire-primary-bg) text-(--wire-primary-text)' => $item['appearance'] === 'button',
                    'hover:opacity-70' => $item['appearance'] !== 'button',
                ])
            >{{ $item['label'] }}</a>
        @endforeach
    </nav>
@endif
