@props(['item', 'imageHeightClass' => 'max-h-24', 'iconSizeClass' => 'size-14', 'imageRounded' => false, 'cardStyle' => true, 'cardBg' => 'var(--wire-card-bg)', 'cardText' => 'var(--wire-card-text)', 'imageShape' => 'natural', 'imageFit' => 'cover', 'linkCard' => false])

@php
    $shapedImage = in_array($imageShape, ['landscape', 'square', 'portrait'], true);
    $hasCta = $item['cta']['enabled'] && $item['cta']['text'] !== '' && $item['cta']['url'] !== null;
    $hasCardLink = $linkCard && $hasCta;
    $shapedImageClass = $cardStyle
        ? '-mx-6 -mt-6 w-[calc(100%+3rem)] max-w-none'
        : \Illuminate\Support\Arr::toCssClasses(['w-full', 'rounded-(--wire-radius)' => $imageRounded]);
@endphp

<article
    @class([
        'flex h-full flex-col gap-4',
        'wire-card wire-card--feature rounded-(--wire-radius) p-6 shadow-sm' => $cardStyle,
        'overflow-hidden' => $cardStyle && $shapedImage,
        'group relative transition hover:shadow-md' => $hasCardLink,
    ])
    @if ($cardStyle) style="background-color:{{ $cardBg }};color:{{ $cardText }}" @endif
>
    @if ($item['media'] === 'icon' && $item['icon'] !== '')
        <flux:icon :name="$item['icon']" class="{{ $iconSizeClass }} shrink-0" aria-hidden="true" />
    @elseif ($item['image'] && $shapedImage)
        <x-site.blocks.card-image
            :src="$item['image']"
            :alt="$item['alt']"
            :shape="$imageShape"
            :fit="$imageFit"
            :class="$shapedImageClass"
        />
    @elseif ($item['image'])
        <img
            src="{{ $item['image'] }}"
            alt="{{ $item['alt'] }}"
            loading="lazy"
            @class([
                'w-full object-contain object-left',
                $imageHeightClass,
                'rounded-(--wire-radius)' => $imageRounded,
            ])
        />
    @endif

    @if ($item['title'] !== '')
        <h3 @class(['text-lg font-semibold tracking-tight', 'group-hover:text-(--wire-accent)' => $hasCardLink])>
            {{ $item['title'] }}
        </h3>
    @endif

    @if (strip_tags($item['body']) !== '')
        <div @class([
            'wire-prose [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-2 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-(--wire-list-indent) [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-(--wire-list-indent) [&_li]:my-0.5 grow leading-(--wire-body-leading) opacity-80 *:first:mt-0 *:last:mb-0',
            'pointer-events-none relative z-10 [&_a]:pointer-events-auto' => $hasCardLink,
        ])>
            {!! $item['body'] !!}
        </div>
    @endif

    @if ($hasCta)
        <a
            href="{{ $item['cta']['url'] }}"
            @if ($item['cta']['newTab']) target="_blank" rel="noopener noreferrer" @endif
            @class([
                'wire-btn mt-1 inline-flex items-center justify-center rounded-(--wire-btn-radius) px-5 py-2.5 text-sm font-medium transition hover:opacity-90',
                'after:absolute after:inset-0' => $hasCardLink,
            ])
            style="background-color:{{ $item['cta']['bg'] }};color:{{ $item['cta']['fg'] }};--wire-btn-border:var(--wire-primary-border)"
        >{{ $item['cta']['text'] }}</a>
    @endif
</article>
