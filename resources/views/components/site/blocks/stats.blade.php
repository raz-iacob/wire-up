@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $columns = (int) ($content['columns'] ?? 4);
    $columns = in_array($columns, [2, 3, 4], true) ? $columns : 4;
    $layout = $content['layout'] ?? 'plain';
    $layout = in_array($layout, ['plain', 'cards', 'dividers'], true) ? $layout : 'plain';
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];

    $items = collect($rawItems)
        ->map(fn (mixed $item, int $i): array => [
            'value' => $block->text("items.{$i}.value"),
            'label' => $block->text("items.{$i}.label"),
        ])
        ->filter(fn (array $item): bool => $item['value'] !== '' || $item['label'] !== '')
        ->values();

    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';

    $gridCols = match ($columns) {
        2 => 'sm:grid-cols-2',
        3 => 'grid-cols-2 lg:grid-cols-3',
        default => 'grid-cols-2 lg:grid-cols-4',
    };

    $cardBg = $hasBg ? 'var(--wire-body-bg)' : 'var(--wire-card-bg)';
    $cardText = $hasBg ? 'var(--wire-body-text)' : 'var(--wire-card-text)';
@endphp

@if ($items->isNotEmpty())
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            @if ($hasHeading)
                <div class="mb-12">
                    @if (strip_tags($heading) !== '')
                        <div class="tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                    @endif
                    @if (strip_tags($intro) !== '')
                        <div class="mt-3 leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                    @endif
                </div>
            @endif

            @if ($layout === 'dividers')
                <div class="flex flex-col sm:flex-row">
                    @foreach ($items as $item)
                        <div @class([
                            'flex-1 px-6 text-center',
                            'mt-8 border-t border-(--wire-card-border) pt-8 sm:mt-0 sm:border-t-0 sm:border-l sm:pt-0' => ! $loop->first,
                        ]) wire:key="stat-{{ $loop->index }}">
                            <div class="font-bold tracking-tight text-(--wire-primary-bg) text-[length:calc(var(--wire-heading-size)*1.6)]">{{ $item['value'] }}</div>
                            @if ($item['label'] !== '')
                                <div class="mt-2 leading-snug opacity-80">{{ $item['label'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid {{ $gridCols }} gap-6">
                    @foreach ($items as $item)
                        <div @class([
                            'text-center',
                            'rounded-(--wire-radius) p-6 shadow-sm' => $layout === 'cards',
                        ]) @if ($layout === 'cards') style="background-color:{{ $cardBg }};color:{{ $cardText }}" @endif wire:key="stat-{{ $loop->index }}">
                            <div class="font-bold tracking-tight text-(--wire-primary-bg) text-[length:calc(var(--wire-heading-size)*1.6)]">{{ $item['value'] }}</div>
                            @if ($item['label'] !== '')
                                <div class="mt-2 leading-snug opacity-80">{{ $item['label'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
