@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $rawMedia = is_array($content['media'] ?? null) ? $content['media'] : [];
    $columns = max(2, min(6, (int) ($content['columns'] ?? 3)));
    $lightbox = (bool) ($content['lightbox'] ?? true);
    $hasBg = (bool) ($content['hasBackground'] ?? false);

    $items = collect($rawMedia)
        ->map(fn (mixed $item, int $i): array => [
            'isVideo' => $block->isVideo("media.{$i}"),
            'poster' => $block->posterUrl("media.{$i}", ['w' => 800, 'h' => 800]),
            'full' => $block->isVideo("media.{$i}") ? $block->fileUrl("media.{$i}") : $block->imageUrl("media.{$i}", ['w' => 1600]),
            'alt' => $block->imageAlt("media.{$i}"),
            'caption' => (string) data_get($item, 'metadata.caption', ''),
        ])
        ->filter(fn (array $item): bool => $item['poster'] !== null || $item['full'] !== null)
        ->values();

    $gridClass = match ($columns) {
        2 => 'grid-cols-2',
        4 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
        5 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
        6 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
        default => 'grid-cols-2 sm:grid-cols-3',
    };
@endphp

<section
    @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])
    @if ($lightbox)
        x-data="{
            open: false,
            index: 0,
            items: @js($items->map(fn (array $item): array => ['isVideo' => $item['isVideo'], 'full' => $item['full'], 'alt' => $item['alt'], 'caption' => $item['caption']])->all()),
            show(i) { this.index = i; this.open = true; document.body.style.overflow = 'hidden'; },
            close() { this.open = false; document.body.style.overflow = ''; },
            prev() { this.index = (this.index - 1 + this.items.length) % this.items.length; },
            next() { this.index = (this.index + 1) % this.items.length; },
            get current() { return this.items[this.index] || {}; },
        }"
        x-on:keydown.escape.window="close()"
        x-on:keydown.arrow-left.window="open && prev()"
        x-on:keydown.arrow-right.window="open && next()"
    @endif
>
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
        @if ($heading)
            <div class="mb-8 tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
        @endif

        @if ($items->isNotEmpty())
            <div class="grid {{ $gridClass }} gap-4">
                @foreach ($items as $i => $item)
                    <figure class="m-0">
                        <div class="relative aspect-square overflow-hidden rounded-[calc(var(--wire-radius)*1.5)] bg-black/5 dark:bg-white/10">
                            @if ($lightbox || ! $item['isVideo'])
                                <{{ $lightbox ? 'button' : 'div' }} class="size-full {{ $lightbox ? 'group cursor-zoom-in' : '' }}" @if ($lightbox) type="button" x-on:click="show({{ $i }})" @endif>
                                    @if ($item['poster'])
                                        <img src="{{ $item['poster'] }}" alt="{{ $item['alt'] }}" loading="lazy" class="size-full object-cover transition group-hover:scale-105" />
                                    @else
                                        <span class="flex size-full items-center justify-center text-current/60"><flux:icon name="video-camera" class="size-10" /></span>
                                    @endif

                                    @if ($item['isVideo'])
                                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                            <span class="flex size-14 items-center justify-center rounded-full bg-black/55 text-white"><flux:icon name="play" class="size-7" /></span>
                                        </span>
                                    @endif
                                </{{ $lightbox ? 'button' : 'div' }}>
                            @else
                                <video class="size-full object-cover" controls preload="metadata" @if ($item['poster']) poster="{{ $item['poster'] }}" @endif>
                                    <source src="{{ $item['full'] }}" />
                                </video>
                            @endif
                        </div>

                        @if ($item['caption'] !== '')
                            <figcaption class="mt-2 text-sm opacity-80">{{ $item['caption'] }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        @endif
    </div>

    @if ($lightbox)
        <div
            x-cloak
            x-show="open"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
            x-on:click.self="close()"
        >
            <button type="button" class="absolute right-4 top-4 text-white/70 hover:text-white" x-on:click="close()" aria-label="{{ __('Close') }}"><flux:icon name="x-mark" class="size-8" /></button>

            <template x-if="items.length > 1">
                <button type="button" class="absolute left-2 md:left-6 text-white/70 hover:text-white" x-on:click="prev()" aria-label="{{ __('Previous') }}"><flux:icon name="chevron-left" class="size-10" /></button>
            </template>

            <template x-if="items.length > 1">
                <button type="button" class="absolute right-2 md:right-6 text-white/70 hover:text-white" x-on:click="next()" aria-label="{{ __('Next') }}"><flux:icon name="chevron-right" class="size-10" /></button>
            </template>

            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col items-center justify-center gap-3">
                <template x-if="current.isVideo">
                    <video class="max-h-[80vh] w-auto rounded-[calc(var(--wire-radius)*1.5)]" controls autoplay :src="current.full"></video>
                </template>
                <template x-if="!current.isVideo">
                    <img class="max-h-[80vh] w-auto rounded-[calc(var(--wire-radius)*1.5)] object-contain" :src="current.full" :alt="current.alt" />
                </template>
                <p class="text-center text-sm text-white/80" x-show="current.caption" x-text="current.caption"></p>
            </div>
        </div>
    @endif
</section>
