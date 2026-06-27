@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $image = $block->imageUrl('image', ['w' => 1600], 'desktop');
    $imageMobile = $block->imageUrl('image', ['w' => 1080, 'h' => 1350], 'mobile');
    $alt = $block->imageAlt('image');
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $full = ($content['width'] ?? 'normal') === 'full';
    $linkUrl = $block->ctaUrl('imageLink');
    $newTab = $block->ctaOpensNewTab('imageLink');

    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';
    $imgClass = $full ? 'w-full h-auto' : 'w-full h-auto rounded-(--wire-radius) shadow-sm';
@endphp

<section @class([
    'w-full',
    'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
    ($pad ?? 'py-16') => $hasBg,
])>
    @if ($hasHeading)
        <div class="mx-auto mb-8 max-w-(--wire-container) px-(--wire-gutter)">
            @if (strip_tags($heading) !== '')
                <div class="tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
            @endif
            @if (strip_tags($intro) !== '')
                <div class="mt-3 leading-relaxed opacity-80 [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
            @endif
        </div>
    @endif

    @if ($image)
        <div @class(['mx-auto max-w-(--wire-container) px-(--wire-gutter)' => ! $full])>
            <figure class="m-0">
                @if ($linkUrl)
                    <a href="{{ $linkUrl }}" @if ($newTab) target="_blank" rel="noopener noreferrer" @endif class="block">
                        <picture class="contents">
                            @if ($imageMobile)
                                <source media="(max-width: 767px)" srcset="{{ $imageMobile }}" />
                            @endif
                            <img src="{{ $image }}" alt="{{ $alt }}" loading="lazy" class="{{ $imgClass }}" />
                        </picture>
                    </a>
                @else
                    <picture class="contents">
                        @if ($imageMobile)
                            <source media="(max-width: 767px)" srcset="{{ $imageMobile }}" />
                        @endif
                        <img src="{{ $image }}" alt="{{ $alt }}" loading="lazy" class="{{ $imgClass }}" />
                    </picture>
                @endif
            </figure>
        </div>
    @endif
</section>
