@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $name = $block->text('name');
    $address = $block->text('address');
    $hours = $block->text('hours');
    $phone = mb_trim((string) ($content['phone'] ?? ''));
    $email = mb_trim((string) ($content['email'] ?? ''));
    $reverse = (bool) ($content['reverseLayout'] ?? false);
    $hasBg = (bool) ($content['hasBackground'] ?? false);

    $mapRaw = mb_trim((string) ($content['map'] ?? ''));
    $isUrl = str_starts_with($mapRaw, 'http');
    $mapSrc = $isUrl ? $mapRaw : 'https://www.google.com/maps?q='.urlencode($mapRaw).'&output=embed';

    $dirQuery = $isUrl ? $address : $mapRaw;
    $dirUrl = $dirQuery !== '' ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($dirQuery) : null;

    $directions = [
        'enabled' => (bool) ($content['directions']['enabled'] ?? false),
        'text' => $block->text('directions.text'),
        'url' => $dirUrl,
        'bg' => ($content['directions']['bg'] ?? null) ?: 'var(--wire-primary-bg)',
        'fg' => ($content['directions']['textColor'] ?? null) ?: 'var(--wire-primary-text)',
    ];
    $showDirections = $directions['enabled'] && $directions['text'] !== '' && $directions['url'] !== null;
@endphp

<section
    @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])
>
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
        @if ($heading)
            <div class="mb-8 tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
        @endif

        <div @class([
            'grid gap-8 md:grid-cols-2 md:items-center md:gap-10' => $mapRaw !== '',
        ])>
            @if ($mapRaw !== '')
                <div @class(['md:order-last' => $reverse])>
                    <iframe
                        src="{{ $mapSrc }}"
                        title="{{ strip_tags($heading) ?: __('Map') }}"
                        aria-label="{{ strip_tags($heading) ?: __('Map') }}"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                        class="aspect-video w-full rounded-[calc(var(--wire-radius)*1.5)] border-0"
                    ></iframe>
                </div>
            @endif

            <div class="flex flex-col gap-5">
                @if ($name !== '')
                    <p class="text-lg font-semibold">{{ $name }}</p>
                @endif

                @if ($address !== '')
                    <p class="whitespace-pre-line opacity-90">{{ $address }}</p>
                @endif

                @if (strip_tags($hours) !== '')
                    <div class="opacity-90 [&_a]:underline [&_p]:whitespace-pre-wrap [&>p]:my-1 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-5 [&_ol]:pl-5 *:first:mt-0 *:last:mb-0">{!! $hours !!}</div>
                @endif

                @if ($phone !== '' || $email !== '')
                    <div class="flex flex-col gap-1">
                        @if ($phone !== '')
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="underline">{{ $phone }}</a>
                        @endif
                        @if ($email !== '')
                            <a href="mailto:{{ $email }}" class="underline">{{ $email }}</a>
                        @endif
                    </div>
                @endif

                @if ($showDirections)
                    <div class="mt-2">
                        <a
                            href="{{ $directions['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center rounded-(--wire-radius) px-6 py-3 text-base font-medium transition hover:opacity-90"
                            style="background-color:{{ $directions['bg'] }};color:{{ $directions['fg'] }}"
                        >{{ $directions['text'] }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
