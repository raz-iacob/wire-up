@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $body = $block->text('body');
    $image = $block->imageUrl('image', ['w' => 1200]);
    $reverse = (bool) ($content['reverseLayout'] ?? false);
    $hasBg = (bool) ($content['hasBackground'] ?? false);

    $defaultBg = ['ctaPrimary' => 'var(--wire-primary-bg)', 'ctaSecondary' => 'var(--wire-secondary-bg)'];
    $defaultText = ['ctaPrimary' => 'var(--wire-primary-text)', 'ctaSecondary' => 'var(--wire-secondary-text)'];
    $defaultBorder = ['ctaPrimary' => 'var(--wire-primary-border)', 'ctaSecondary' => 'var(--wire-secondary-border)'];

    $ctas = collect(['ctaPrimary', 'ctaSecondary'])
        ->map(fn (string $key): array => [
            'text' => $block->text("{$key}.text"),
            'url' => $block->ctaUrl($key),
            'newTab' => $block->ctaOpensNewTab($key),
            'enabled' => (bool) ($content[$key]['enabled'] ?? false),
            'bg' => ($content[$key]['bg'] ?? null) ?: $defaultBg[$key],
            'fg' => ($content[$key]['textColor'] ?? null) ?: $defaultText[$key],
            'border' => $defaultBorder[$key],
        ])
        ->filter(fn (array $cta): bool => $cta['enabled'] && $cta['text'] !== '' && $cta['url'] !== null)
        ->values();
@endphp

<section
    @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])
>
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
        <div @class([
            'md:grid md:grid-cols-2 md:items-center md:gap-10' => $image,
        ])>
            <div class="flex flex-col gap-5">
                @if ($heading)
                    <div class="tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                @endif

                @if (strip_tags($body) !== '')
                    <div class="max-w-none leading-relaxed [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-4 *:first:mt-0 *:last:mb-0">
                        {!! $body !!}
                    </div>
                @endif

                @if ($ctas->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-4">
                        @foreach ($ctas as $cta)
                            <a
                                href="{{ $cta['url'] }}"
                                @if ($cta['newTab']) target="_blank" rel="noopener noreferrer" @endif
                                class="wire-btn inline-flex items-center justify-center rounded-(--wire-radius) px-6 py-3 text-base font-medium transition hover:opacity-90"
                                style="background-color:{{ $cta['bg'] }};color:{{ $cta['fg'] }};--wire-btn-border:{{ $cta['border'] }}"
                            >{{ $cta['text'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($image)
                <div @class(['mt-6 md:mt-0', 'md:order-first' => ! $reverse])>
                    <img
                        src="{{ $image }}"
                        alt="{{ $block->imageAlt('image') }}"
                        loading="lazy"
                        class="w-full rounded-[calc(var(--wire-radius)*1.5)] object-cover"
                    />
                </div>
            @endif
        </div>
    </div>
</section>
