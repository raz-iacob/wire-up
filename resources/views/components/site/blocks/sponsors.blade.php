@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];
    $layout = $content['layout'] ?? 'grid';
    $layout = in_array($layout, ['grid', 'marquee', 'grouped'], true) ? $layout : 'grid';
    $columns = max(3, min(6, (int) ($content['columns'] ?? 4)));
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $grayscale = (bool) ($content['grayscale'] ?? false);
    $showNames = (bool) ($content['showNames'] ?? false);

    $items = collect($rawItems)
        ->map(fn (mixed $item, int $i): array => [
            'logo' => $block->imageUrl("items.{$i}.logo", ['w' => 400]),
            'name' => $block->text("items.{$i}.name"),
            'alt' => $block->text("items.{$i}.name") ?: $block->imageAlt("items.{$i}.logo"),
            'link' => mb_trim((string) data_get($item, 'link', '')),
            'tier' => mb_trim((string) data_get($item, 'tier', '')),
        ])
        ->filter(fn (array $item): bool => $item['logo'] !== null)
        ->values();

    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';

    $gridClassFor = fn (int $cols): string => match (max(2, min(8, $cols))) {
        2 => 'grid-cols-2',
        3 => 'grid-cols-2 sm:grid-cols-3',
        5 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
        6 => 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6',
        7 => 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-7',
        8 => 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8',
        default => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
    };

    $sizeFor = fn (int $cols): string => match (true) {
        $cols <= 4 => 'lg',
        $cols <= 6 => 'md',
        default => 'sm',
    };

    $tierColumns = [4, 5, 6, 8];
@endphp

<section @class([
    'w-full py-18',
    'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
])>
    @if ($hasHeading)
        <div class="mx-auto mb-12 max-w-(--wire-container) px-6">
            @if (strip_tags($heading) !== '')
                <div class="tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
            @endif
            @if (strip_tags($intro) !== '')
                <div class="mt-3 leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
            @endif
        </div>
    @endif

    @if ($items->isNotEmpty())
        @if ($layout === 'marquee')
            <div class="group w-full overflow-hidden">
                <div class="flex w-max animate-marquee items-center group-hover:[animation-play-state:paused]">
                    @foreach ($items as $item)
                        <x-site.blocks.sponsor-logo :item="$item" :grayscale="$grayscale" :show-name="$showNames" class="shrink-0 pr-12 md:pr-16" wire:key="sponsor-marquee-{{ $loop->index }}" />
                    @endforeach
                    @foreach ($items as $item)
                        <x-site.blocks.sponsor-logo :item="$item" :grayscale="$grayscale" :show-name="$showNames" class="shrink-0 pr-12 md:pr-16" aria-hidden="true" wire:key="sponsor-marquee-clone-{{ $loop->index }}" />
                    @endforeach
                </div>
            </div>
        @elseif ($layout === 'grouped')
            <div class="mx-auto flex max-w-(--wire-container) flex-col gap-14 px-6">
                @foreach ($items->groupBy('tier') as $tier => $group)
                    @php($tierCols = $tierColumns[min($loop->index, count($tierColumns) - 1)])
                    <div class="flex flex-col gap-6">
                        @if ($tier !== '')
                            <div class="flex items-center gap-4">
                                <h3 class="text-sm font-semibold uppercase tracking-wider opacity-70">{{ $tier }}</h3>
                                <span class="h-px grow border-t border-dashed border-current/20"></span>
                            </div>
                        @endif

                        <div class="grid {{ $gridClassFor($tierCols) }} items-center gap-x-8 gap-y-10">
                            @foreach ($group as $item)
                                <x-site.blocks.sponsor-logo :item="$item" :grayscale="$grayscale" :show-name="$showNames" :size="$sizeFor($tierCols)" wire:key="sponsor-grouped-{{ $tier }}-{{ $loop->index }}" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mx-auto max-w-(--wire-container) px-6">
                <div class="grid {{ $gridClassFor($columns) }} items-center gap-x-8 gap-y-10">
                    @foreach ($items as $item)
                        <x-site.blocks.sponsor-logo :item="$item" :grayscale="$grayscale" :show-name="$showNames" :size="$sizeFor($columns)" wire:key="sponsor-grid-{{ $loop->index }}" />
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</section>
