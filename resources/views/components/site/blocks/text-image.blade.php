@props(['block'])

@php
    $content = $block->content ?? [];
    $headingLevel = \App\Services\BlockHeading::level($content['headingLevel'] ?? null);
    $heading = $block->text('heading');
    $body = $block->text('body');
    $image = $block->imageUrl('image', ['w' => 1200]);
    $reverse = (bool) ($content['reverseLayout'] ?? false);
    $hasBg = (bool) ($content['hasBackground'] ?? false);

    $sectionStyle = $hasBg ? \App\Services\BlockColor::style([
        'background-color' => $content['bg'] ?? null,
        'color' => $content['textColor'] ?? null,
    ]) : '';

    $split = in_array($content['columnSplit'] ?? 'even', ['even', 'text-wide', 'image-wide'], true)
        ? ($content['columnSplit'] ?? 'even')
        : 'even';
    $textFirst = $reverse;
    $gridCols = match ($split) {
        'text-wide' => $textFirst ? 'md:grid-cols-[3fr_2fr]' : 'md:grid-cols-[2fr_3fr]',
        'image-wide' => $textFirst ? 'md:grid-cols-[2fr_3fr]' : 'md:grid-cols-[3fr_2fr]',
        default => 'md:grid-cols-2',
    };

    $ratioClass = match ($content['imageRatio'] ?? 'auto') {
        '1:1' => 'aspect-square h-full',
        '4:3' => 'aspect-4/3 h-full',
        '3:2' => 'aspect-3/2 h-full',
        '16:9' => 'aspect-video h-full',
        '3:4' => 'aspect-3/4 h-full',
        default => '',
    };

    $imageRadiusClass = match ($content['imageRadius'] ?? 'default') {
        'none' => 'rounded-none',
        'small' => 'rounded-(--wire-radius)',
        'large' => 'rounded-[calc(var(--wire-radius)*3)]',
        'full' => 'rounded-full',
        default => 'rounded-[calc(var(--wire-radius)*1.5)]',
    };

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
    @if ($sectionStyle !== '') style="{{ $sectionStyle }}" @endif
>
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
        <div @class([
            'md:grid md:items-center md:gap-10' => $image,
            $gridCols => $image,
        ])>
            <div class="flex flex-col gap-5">
                @if ($heading)
                    <div class="[&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size) tracking-tight text-(--wire-heading)">
                        <x-site.blocks.heading :html="$heading" :level="$headingLevel" />
                    </div>
                @endif

                @if (strip_tags($body) !== '')
                    <div class="wire-prose [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-4 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-(--wire-list-indent) [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-(--wire-list-indent) [&_li]:my-1 max-w-none leading-(--wire-body-leading) *:first:mt-0 *:last:mb-0">
                        {!! $body !!}
                    </div>
                @endif

                @if ($ctas->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-4">
                        @foreach ($ctas as $cta)
                            <a
                                href="{{ $cta['url'] }}"
                                @if ($cta['newTab']) target="_blank" rel="noopener noreferrer" @endif
                                class="wire-btn inline-flex items-center justify-center rounded-(--wire-btn-radius) px-6 py-3 text-base font-medium transition hover:opacity-90"
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
                        @class(['w-full object-cover', $ratioClass, $imageRadiusClass])
                    />
                </div>
            @endif
        </div>
    </div>
</section>
