@props(['page'])

<article class="w-full">
    @foreach ($page->blocks as $block)
        @php($anchor = $block->type->hasAnchor() ? trim((string) ($block->content['anchor'] ?? '')) : '')
        @if ($anchor !== '')
            <div id="{{ $anchor }}" class="scroll-mt-24">
                @includeIf($block->type->frontendView(), ['block' => $block])
            </div>
        @else
            @includeIf($block->type->frontendView(), ['block' => $block])
        @endif
    @endforeach
</article>
