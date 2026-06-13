@props(['links' => []])

@if (! empty($links))
    <div {{ $attributes->merge(['class' => 'flex items-center gap-4']) }}>
        @foreach ($links as $platform => $url)
            <a
                href="{{ $url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="opacity-70 transition-opacity hover:opacity-100"
                aria-label="{{ ucfirst($platform) }}"
            >
                <x-site.social-icon :platform="$platform" />
            </a>
        @endforeach
    </div>
@endif
