@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];
    $layout = $content['layout'] ?? 'grid';
    $layout = in_array($layout, ['grid', 'carousel', 'single', 'split'], true) ? $layout : 'grid';
    $columns = (int) ($content['columns'] ?? 3) === 2 ? 2 : 3;
    $hasBg = (bool) ($content['hasBackground'] ?? false);

    $items = collect($rawItems)
        ->map(fn (mixed $item, int $i): array => [
            'quote' => $block->text("items.{$i}.quote"),
            'author' => $block->text("items.{$i}.author"),
            'role' => $block->text("items.{$i}.role"),
            'avatar' => $block->imageUrl("items.{$i}.avatar", ['w' => 200, 'h' => 200]),
            'alt' => $block->imageAlt("items.{$i}.avatar") ?: $block->text("items.{$i}.author"),
            'rating' => max(0, min(5, (int) data_get($item, 'rating', 0))),
        ])
        ->filter(fn (array $item): bool => strip_tags($item['quote']) !== '' || $item['author'] !== '')
        ->values();

    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';
    $gridCols = $columns === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-2 lg:grid-cols-3';
    $cardBg = ($content['cardBg'] ?? null) ?: ($hasBg ? 'var(--wire-body-bg)' : 'var(--wire-card-bg)');
    $cardText = ($content['cardText'] ?? null) ?: ($hasBg ? 'var(--wire-body-text)' : 'var(--wire-card-text)');
    $starColor = ($content['amberStars'] ?? false) ? 'text-amber-400' : 'text-(--wire-primary-bg)';
@endphp

<section @class([
    'w-full',
    'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
    ($pad ?? 'py-16') => $hasBg,
])>
    @if ($layout === 'carousel')
        <div
            x-data="{
                atStart: true,
                atEnd: false,
                scroll(dir) { const t = this.$refs.track; t.scrollBy({ left: dir * t.clientWidth * 0.8, behavior: 'smooth' }); },
                update() { const t = this.$refs.track; this.atStart = t.scrollLeft <= 1; this.atEnd = Math.ceil(t.scrollLeft + t.offsetWidth) >= t.scrollWidth; },
            }"
            x-init="$nextTick(() => update())"
        >
            @if ($hasHeading || $items->count() > 1)
                <div class="relative mx-auto max-w-(--wire-container) px-(--wire-gutter)">
                    @if ($hasHeading)
                        <div>
                            @if (strip_tags($heading) !== '')
                                <div class="tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                            @endif
                            @if (strip_tags($intro) !== '')
                                <div class="mt-3 leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                            @endif
                        </div>
                    @endif

                    @if ($items->count() > 1)
                        <div @class([
                            'hidden gap-2 sm:flex',
                            'absolute right-6 top-0' => $hasHeading,
                            'justify-end' => ! $hasHeading,
                        ])>
                            <flux:button square variant="subtle" icon="chevron-left" x-on:click="scroll(-1)" x-bind:disabled="atStart" class="disabled:opacity-40" :aria-label="__('Previous')" />
                            <flux:button square variant="subtle" icon="chevron-right" x-on:click="scroll(1)" x-bind:disabled="atEnd" class="disabled:opacity-40" :aria-label="__('Next')" />
                        </div>
                    @endif
                </div>
            @endif

            @if ($items->isNotEmpty())
                <div
                    x-ref="track"
                    x-on:scroll="update()"
                    class="mt-8 flex items-stretch gap-6 overflow-x-auto scroll-smooth snap-x snap-mandatory px-[max(var(--wire-gutter),calc((100%-var(--wire-container))/2+var(--wire-gutter)))] pb-2 scroll-pl-[max(var(--wire-gutter),calc((100%-var(--wire-container))/2+var(--wire-gutter)))] scrollbar-none [&::-webkit-scrollbar]:hidden"
                >
                    @foreach ($items as $item)
                        <article class="flex w-[86vw] shrink-0 snap-start flex-col gap-5 rounded-(--wire-radius) p-8 shadow-sm sm:w-96 md:w-120" style="background-color:{{ $cardBg }};color:{{ $cardText }}">
                            @if ($item['rating'] > 0)
                                <div class="flex gap-0.5">
                                    @for ($star = 1; $star <= 5; $star++)
                                        <flux:icon name="star" variant="{{ $star <= $item['rating'] ? 'solid' : 'outline' }}" class="size-5 {{ $star <= $item['rating'] ? $starColor : 'text-current/20' }}" />
                                    @endfor
                                </div>
                            @endif

                            @if (strip_tags($item['quote']) !== '')
                                <div class="grow leading-relaxed [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $item['quote'] !!}</div>
                            @endif

                            @if ($item['author'] !== '' || $item['avatar'])
                                <div class="flex items-center gap-3">
                                    @if ($item['avatar'])
                                        <img src="{{ $item['avatar'] }}" alt="{{ $item['alt'] }}" loading="lazy" class="size-10 shrink-0 rounded-full object-cover" />
                                    @endif

                                    @if ($item['author'] !== '' || $item['role'] !== '')
                                        <div class="min-w-0">
                                            @if ($item['author'] !== '')
                                                <p class="truncate font-semibold">{{ $item['author'] }}</p>
                                            @endif
                                            @if ($item['role'] !== '')
                                                <p class="truncate text-sm opacity-80">{{ $item['role'] }}</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            @if ($layout === 'split')
                <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:items-start lg:gap-16">
                    <div class="lg:sticky lg:top-24">
                        @if (strip_tags($heading) !== '')
                            <div class="tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                        @endif
                        @if (strip_tags($intro) !== '')
                            <div class="mt-4 max-w-prose leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                        @endif
                    </div>

                    @if ($items->isNotEmpty())
                        <div class="flex flex-col gap-6">
                            @foreach ($items as $item)
                                <article class="flex items-start gap-5 rounded-(--wire-radius) p-6 shadow-sm" style="background-color:{{ $cardBg }};color:{{ $cardText }}">
                                    @if ($item['avatar'])
                                        <img src="{{ $item['avatar'] }}" alt="{{ $item['alt'] }}" loading="lazy" class="size-14 shrink-0 rounded-full object-cover" />
                                    @endif

                                    <div class="min-w-0">
                                        @if ($item['author'] !== '')
                                            <p class="font-semibold">{{ $item['author'] }}</p>
                                        @endif
                                        @if ($item['role'] !== '')
                                            <p class="text-sm opacity-80">{{ $item['role'] }}</p>
                                        @endif
                                        @if ($item['rating'] > 0)
                                            <div class="mt-3 flex gap-0.5">
                                                @for ($star = 1; $star <= 5; $star++)
                                                    <flux:icon name="star" variant="{{ $star <= $item['rating'] ? 'solid' : 'outline' }}" class="size-5 {{ $star <= $item['rating'] ? $starColor : 'text-current/20' }}" />
                                                @endfor
                                            </div>
                                        @endif
                                        @if (strip_tags($item['quote']) !== '')
                                            <div class="mt-3 leading-relaxed [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $item['quote'] !!}</div>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                @if ($hasHeading)
                    <div>
                        @if (strip_tags($heading) !== '')
                            <div class="tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                        @endif
                        @if (strip_tags($intro) !== '')
                            <div class="mt-3 leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                        @endif
                    </div>
                @endif

                @if ($items->isNotEmpty())
                    @if ($layout === 'single')
                        <div class="mx-auto flex max-w-3xl flex-col gap-12 {{ $hasHeading ? 'mt-12' : '' }}">
                            @foreach ($items as $item)
                                <figure class="flex flex-col gap-6">
                                    @if ($item['rating'] > 0)
                                        <div class="flex gap-1">
                                            @for ($star = 1; $star <= 5; $star++)
                                                <flux:icon name="star" variant="{{ $star <= $item['rating'] ? 'solid' : 'outline' }}" class="size-6 {{ $star <= $item['rating'] ? $starColor : 'text-current/20' }}" />
                                            @endfor
                                        </div>
                                    @endif

                                    @if (strip_tags($item['quote']) !== '')
                                        <blockquote class="text-2xl font-medium leading-relaxed tracking-tight [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $item['quote'] !!}</blockquote>
                                    @endif

                                    @if ($item['author'] !== '' || $item['avatar'])
                                        <figcaption class="flex items-center gap-4">
                                            @if ($item['avatar'])
                                                <img src="{{ $item['avatar'] }}" alt="{{ $item['alt'] }}" loading="lazy" class="size-12 shrink-0 rounded-full object-cover" />
                                            @endif

                                            @if ($item['author'] !== '' || $item['role'] !== '')
                                                <div class="min-w-0">
                                                    @if ($item['author'] !== '')
                                                        <p class="truncate font-semibold">{{ $item['author'] }}</p>
                                                    @endif
                                                    @if ($item['role'] !== '')
                                                        <p class="truncate text-sm opacity-80">{{ $item['role'] }}</p>
                                                    @endif
                                                </div>
                                            @endif
                                        </figcaption>
                                    @endif
                                </figure>
                            @endforeach
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-6 {{ $gridCols }} {{ $hasHeading ? 'mt-12' : '' }}">
                            @foreach ($items as $item)
                                <article class="flex h-full flex-col gap-5 rounded-(--wire-radius) p-6 shadow-sm" style="background-color:{{ $cardBg }};color:{{ $cardText }}">
                                    @if ($item['rating'] > 0)
                                        <div class="flex gap-0.5">
                                            @for ($star = 1; $star <= 5; $star++)
                                                <flux:icon name="star" variant="{{ $star <= $item['rating'] ? 'solid' : 'outline' }}" class="size-5 {{ $star <= $item['rating'] ? $starColor : 'text-current/20' }}" />
                                            @endfor
                                        </div>
                                    @endif

                                    @if (strip_tags($item['quote']) !== '')
                                        <div class="grow leading-relaxed [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $item['quote'] !!}</div>
                                    @endif

                                    @if ($item['author'] !== '' || $item['avatar'])
                                        <div class="flex items-center gap-3">
                                            @if ($item['avatar'])
                                                <img src="{{ $item['avatar'] }}" alt="{{ $item['alt'] }}" loading="lazy" class="size-10 shrink-0 rounded-full object-cover" />
                                            @endif

                                            @if ($item['author'] !== '' || $item['role'] !== '')
                                                <div class="min-w-0">
                                                    @if ($item['author'] !== '')
                                                        <p class="truncate font-semibold">{{ $item['author'] }}</p>
                                                    @endif
                                                    @if ($item['role'] !== '')
                                                        <p class="truncate text-sm opacity-80">{{ $item['role'] }}</p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                @endif
            @endif
        </div>
    @endif
</section>
