@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];
    $layout = $content['layout'] ?? 'grid';
    $layout = in_array($layout, ['grid', 'carousel', 'single', 'split', 'spotlight'], true) ? $layout : 'grid';
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

    $hasHeading = $heading !== '' || strip_tags($intro) !== '';
    $headCenter = in_array($layout, ['grid', 'spotlight'], true);
    $gridCols = $columns === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-2 lg:grid-cols-3';
    $fill = $hasBg
        ? 'bg-[color-mix(in_srgb,var(--wire-body-bg)_20%,transparent)]'
        : 'bg-[color-mix(in_srgb,var(--wire-card-bg)_20%,transparent)]';
@endphp

<section
    class="w-full py-18"
    @if ($hasBg) style="background-color:var(--wire-card-bg);color:var(--wire-card-text)" @endif
>
    <div class="mx-auto max-w-7xl px-6">
        @if ($layout === 'split')
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:items-start lg:gap-16">
                <div class="lg:sticky lg:top-24">
                    @if ($heading)
                        <div class="tracking-tight [&>p]:m-0 [&_a]:underline" style="font-size:var(--wire-heading-size, 1.5rem)">{!! $heading !!}</div>
                    @endif
                    @if (strip_tags($intro) !== '')
                        <div class="mt-4 max-w-prose leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                    @endif
                </div>

                @if ($items->isNotEmpty())
                    <div class="flex flex-col gap-6">
                        @foreach ($items as $item)
                            <article class="flex items-start gap-5 rounded-(--wire-radius) border border-(--wire-card-border) p-6">
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
                                                <flux:icon name="star" variant="{{ $star <= $item['rating'] ? 'solid' : 'outline' }}" class="size-5 {{ $star <= $item['rating'] ? 'text-(--wire-primary-bg)' : 'text-current/20' }}" />
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
                <div class="@if ($headCenter) mx-auto max-w-2xl text-center @endif">
                    @if ($heading)
                        <div class="tracking-tight [&>p]:m-0 [&_a]:underline" style="font-size:var(--wire-heading-size, 1.5rem)">{!! $heading !!}</div>
                    @endif
                    @if (strip_tags($intro) !== '')
                        <div class="mt-3 leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0 @if (! $headCenter) max-w-3xl @endif">{!! $intro !!}</div>
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
                                            <flux:icon name="star" variant="{{ $star <= $item['rating'] ? 'solid' : 'outline' }}" class="size-6 {{ $star <= $item['rating'] ? 'text-(--wire-primary-bg)' : 'text-current/20' }}" />
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
                @elseif ($layout === 'spotlight')
                    <div class="grid grid-cols-1 gap-6 {{ $gridCols }} {{ $hasHeading ? 'mt-12' : '' }}">
                        @foreach ($items as $item)
                            <article class="flex h-full flex-col items-center gap-4 rounded-(--wire-radius) p-8 text-center {{ $loop->first ? 'border border-(--wire-primary-bg)' : $fill }}">
                                @if ($item['avatar'])
                                    <img src="{{ $item['avatar'] }}" alt="{{ $item['alt'] }}" loading="lazy" class="size-20 shrink-0 rounded-full object-cover" />
                                @endif

                                <div class="h-px w-full bg-current/10"></div>

                                @if ($item['author'] !== '')
                                    <p class="font-semibold">{{ $item['author'] }}</p>
                                @endif
                                @if ($item['role'] !== '')
                                    <p class="text-sm opacity-80">{{ $item['role'] }}</p>
                                @endif

                                @if ($item['rating'] > 0)
                                    <div class="flex gap-0.5">
                                        @for ($star = 1; $star <= 5; $star++)
                                            <flux:icon name="star" variant="{{ $star <= $item['rating'] ? 'solid' : 'outline' }}" class="size-5 {{ $star <= $item['rating'] ? 'text-(--wire-primary-bg)' : 'text-current/20' }}" />
                                        @endfor
                                    </div>
                                @endif

                                @if (strip_tags($item['quote']) !== '')
                                    <div class="leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $item['quote'] !!}</div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    @php($isCarousel = $layout === 'carousel')

                    @if ($isCarousel)
                        <div class="relative {{ $hasHeading ? 'mt-12' : '' }}" x-data="{ scroll(dir) { this.$refs.track.scrollBy({ left: dir * this.$refs.track.clientWidth, behavior: 'smooth' }); } }">
                            <div x-ref="track" class="flex gap-6 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @else
                        <div class="grid grid-cols-1 gap-6 {{ $gridCols }} {{ $hasHeading ? 'mt-12' : '' }}">
                    @endif

                            @foreach ($items as $item)
                                <article class="flex h-full flex-col gap-5 rounded-(--wire-radius) p-6 {{ $fill }} {{ $isCarousel ? 'snap-start shrink-0 basis-full sm:basis-1/2 lg:basis-1/3' : '' }}">
                                    @if ($item['rating'] > 0)
                                        <div class="flex gap-0.5">
                                            @for ($star = 1; $star <= 5; $star++)
                                                <flux:icon name="star" variant="{{ $star <= $item['rating'] ? 'solid' : 'outline' }}" class="size-5 {{ $star <= $item['rating'] ? 'text-(--wire-primary-bg)' : 'text-current/20' }}" />
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

                    @if ($isCarousel)
                            </div>

                            @if ($items->count() > 1)
                                <div class="mt-4 flex justify-end gap-2">
                                    <flux:button size="sm" variant="filled" icon="chevron-left" x-on:click="scroll(-1)" :aria-label="__('Previous')" />
                                    <flux:button size="sm" variant="filled" icon="chevron-right" x-on:click="scroll(1)" :aria-label="__('Next')" />
                                </div>
                            @endif
                        </div>
                    @else
                        </div>
                    @endif
                @endif
            @endif
        @endif
    </div>
</section>
