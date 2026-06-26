@props(['page'])

@php
    $spacing = \App\Services\SettingsService::current()->blockSpacing();
    $gapClass = match ($spacing) {
        'small' => 'gap-12',
        'large' => 'gap-20',
        default => 'gap-16',
    };
    $padClass = match ($spacing) {
        'small' => 'py-12',
        'large' => 'py-20',
        default => 'py-16',
    };
@endphp

<article @class(['flex w-full flex-col mb-(--wire-gutter)', $gapClass])>
    @foreach ($page->blocks as $block)
        @php($anchor = $block->type->hasAnchor() ? trim((string) ($block->content['anchor'] ?? '')) : '')
        @if ($anchor !== '')
            <div id="{{ $anchor }}" class="scroll-mt-24">
                @includeIf($block->type->frontendView(), ['block' => $block, 'pad' => $padClass])
            </div>
        @else
            @includeIf($block->type->frontendView(), ['block' => $block, 'pad' => $padClass])
        @endif
    @endforeach
</article>
