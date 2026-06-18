@props(['page'])

<article class="w-full">
    @foreach ($page->blocks as $block)
        @includeIf($block->type->frontendView(), ['block' => $block])
    @endforeach
</article>