@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $body = $block->text('body');
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $width = $content['width'] ?? 'normal';
    $narrow = $width === 'narrow';
    $narrowLeft = $width === 'narrow-left';
    $center = ($content['align'] ?? 'left') === 'center';
    $hasContent = strip_tags($heading) !== '' || strip_tags($body) !== '';
@endphp

@if ($hasContent)
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div @class([
            'mx-auto px-(--wire-gutter)',
            'max-w-2xl' => $narrow,
            'max-w-(--wire-container)' => ! $narrow,
            'text-center' => $center,
        ])>
            <div @class(['max-w-2xl' => $narrowLeft])>
                @if (strip_tags($heading) !== '')
                    <div class="[&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline mb-6 text-(length:--wire-heading-size) tracking-tight">
                        {!! $heading !!}
                    </div>
                @endif

                @if (strip_tags($body) !== '')
                    <div class="[&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-4 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-1 max-w-none leading-relaxed *:first:mt-0 *:last:mb-0">
                        {!! $body !!}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
