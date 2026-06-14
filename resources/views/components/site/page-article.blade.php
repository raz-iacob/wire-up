@props(['page'])

<article class="mx-auto w-full max-w-3xl">
    <header class="space-y-4">
        <h1 class="text-(length:--wire-heading-size) font-bold tracking-tight">{{ $page->title }}</h1>
        @if (filled($page->description))
            <p class="text-(length:--wire-body-size) text-(--wire-muted)">{{ $page->description }}</p>
        @endif
    </header>

    {{-- Page content blocks render here once the block builder lands. --}}
</article>
