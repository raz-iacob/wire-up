@props(['links' => [], 'variant' => 'solid'])

@php
    $platforms = config('social.platforms');
@endphp

@if (! empty($links))
    <div {{ $attributes->merge(['class' => 'flex items-center gap-4']) }}>
        @foreach ($links as $platform => $url)
            @php
                $icon = (string) data_get($platforms, "$platform.icon", $platform);
                $iconUrl = Vite::asset("resources/images/socials/{$icon}-{$variant}.svg");
            @endphp
            <a
                href="{{ $url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="opacity-70 transition-opacity hover:opacity-100"
                aria-label="{{ ucfirst($platform) }}"
            >
                <span
                    class="block size-5 bg-current mask-center mask-no-repeat mask-contain"
                    style="mask-image:url('{{ $iconUrl }}'); -webkit-mask-image:url('{{ $iconUrl }}');"
                ></span>
            </a>
        @endforeach
    </div>
@endif
