@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $body = $block->text('body');
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $narrow = ($content['width'] ?? 'normal') === 'narrow';
    $align = $block->plain('align', 'left');
    $align = in_array($align, ['left', 'center', 'right'], true) ? $align : 'left';
    $hasContent = strip_tags($heading) !== '' || strip_tags($body) !== '';
@endphp

@if ($hasContent)
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            <div @class([
                'max-w-2xl' => $narrow,
                'mx-auto' => $narrow && $align === 'center',
                'ms-auto' => $narrow && $align === 'right',
            ])>
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
