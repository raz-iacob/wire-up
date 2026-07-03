@props(['record'])

@php
    $heading = $record->fieldValue('heading', true) ?: $record->title;
    $overview = (string) ($record->fieldValue('overview', true) ?? '');
    $client = (string) ($record->fieldValue('client', false) ?? '');

    $link = (string) ($record->fieldValue('link', false) ?? '');
    $linkHost = $link !== '' ? (string) preg_replace('#^www\.#', '', (string) parse_url($link, PHP_URL_HOST)) : '';
    $linkLabel = $linkHost !== '' ? $linkHost : $link;
@endphp

<section class="w-full">
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter) py-16">
        <div class="flex max-w-3xl flex-col gap-5">
            @if (mb_trim($client) !== '')
                <span class="text-sm font-bold uppercase tracking-wide text-(--wire-accent)">{{ $client }}</span>
            @endif

            <h1 class="tracking-tight text-(length:--wire-heading-size)">{{ $heading }}</h1>

            @if (strip_tags($overview) !== '')
                <div class="max-w-none leading-relaxed [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-4 *:first:mt-0 *:last:mb-0">
                    {!! $overview !!}
                </div>
            @endif

            @if ($link !== '')
                <div>
                    <a
                        href="{{ $link }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-(--wire-radius) border border-current/15 px-4 py-2 text-sm font-medium transition hover:bg-current/5"
                    >
                        <span>{{ $linkLabel }}</span>
                        <flux:icon name="arrow-up-right" class="size-4 text-(--wire-accent)" />
                    </a>
                </div>
            @endif
        </div>
    </div>
</section>
