@props([
    'trail' => [],
    'align' => 'center',
    'separator' => '/',
])

@if (count($trail) > 1)
    <nav aria-label="{{ __('Breadcrumb') }}">
        <ol @class([
            'flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-(--wire-muted)',
            'justify-center' => $align === 'center',
        ])>
            @foreach ($trail as $crumb)
                <li class="flex items-center gap-x-2">
                    @if ($crumb['url'] !== null && ! $loop->last)
                        <a href="{{ $crumb['url'] }}" class="hover:underline">{{ $crumb['label'] }}</a>
                    @else
                        <span @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                    @endif

                    @unless ($loop->last)
                        <span aria-hidden="true">{{ $separator }}</span>
                    @endunless
                </li>
            @endforeach
        </ol>
    </nav>
@endif
