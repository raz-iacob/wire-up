@props(['record'])

@php
    $heading = $record->fieldValue('heading', true) ?: $record->title;
    $overview = (string) ($record->fieldValue('overview', true) ?? '');
@endphp

<section class="w-full">
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter) py-16">
        <div class="flex flex-col gap-5">
            <h1 class="tracking-tight text-(length:--wire-heading-size)">{{ $heading }}</h1>

            @if (strip_tags($overview) !== '')
                <div class="max-w-none leading-relaxed [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-4 *:first:mt-0 *:last:mb-0">
                    {!! $overview !!}
                </div>
            @elseif ($record->description !== '')
                <p class="leading-relaxed">{{ $record->description }}</p>
            @endif
        </div>
    </div>
</section>
