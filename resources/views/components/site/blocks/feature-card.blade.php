@props(['item', 'imageHeightClass' => 'max-h-24', 'imageRounded' => false, 'cardStyle' => true, 'cardBg' => 'var(--wire-card-bg)', 'cardText' => 'var(--wire-card-text)'])

<article
    @class([
        'flex h-full flex-col gap-4',
        'wire-card rounded-(--wire-radius) p-6 shadow-sm' => $cardStyle,
    ])
    @if ($cardStyle) style="background-color:{{ $cardBg }};color:{{ $cardText }}" @endif
>
    @if ($item['image'])
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
        <h3 class="text-lg font-semibold tracking-tight">{{ $item['title'] }}</h3>
    @endif

    @if (strip_tags($item['body']) !== '')
        <div class="grow leading-relaxed opacity-80 [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $item['body'] !!}</div>
    @endif

    @if ($item['cta']['enabled'] && $item['cta']['text'] !== '' && $item['cta']['url'] !== null)
        <a
            href="{{ $item['cta']['url'] }}"
            @if ($item['cta']['newTab']) target="_blank" rel="noopener noreferrer" @endif
            class="wire-btn mt-1 inline-flex items-center justify-center rounded-(--wire-radius) px-5 py-2.5 text-sm font-medium transition hover:opacity-90"
            style="background-color:{{ $item['cta']['bg'] }};color:{{ $item['cta']['fg'] }};--wire-btn-border:var(--wire-primary-border)"
        >{{ $item['cta']['text'] }}</a>
    @endif
</article>
