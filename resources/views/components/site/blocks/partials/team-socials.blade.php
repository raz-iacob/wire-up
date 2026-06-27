@props(['socials' => [], 'justify' => 'start'])

@if (! empty($socials))
    <div {{ $attributes->class([
        'flex items-center gap-4',
        'justify-start' => $justify === 'start',
        'justify-center' => $justify === 'center',
    ]) }}>
        @foreach ($socials as $social)
            <a
                href="{{ $social['href'] }}"
                @if ($social['kind'] === 'mask' || ($social['name'] ?? '') === 'globe-alt') target="_blank" rel="noopener noreferrer" @endif
                class="opacity-70 transition-opacity hover:opacity-100"
                aria-label="{{ $social['label'] }}"
            >
                @if ($social['kind'] === 'mask')
                    <span
                        class="block size-5 bg-current mask-center mask-no-repeat mask-contain"
                        style="mask-image:url('{{ $social['src'] }}'); -webkit-mask-image:url('{{ $social['src'] }}');"
                    ></span>
                @else
                    <flux:icon name="{{ $social['name'] }}" class="size-5" />
                @endif
            </a>
        @endforeach
    </div>
@endif
