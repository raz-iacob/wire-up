@props(['block'])

@php
    $content = $block->content ?? [];
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $layout = in_array($content['layout'] ?? 'grid', ['grid', 'list'], true) ? $content['layout'] : 'grid';
    $columns = (int) ($content['columns'] ?? 3);
    $columns = in_array($columns, [2, 3, 4], true) ? $columns : 3;
    $showImage = (bool) ($content['showImage'] ?? true);
    $heading = $block->text('heading');

    $records = resolve(\App\Services\RecordCollectionQuery::class)->resolve($content);

    $buttonEnabled = (bool) data_get($content, 'button.enabled', false);
    $buttonText = $block->text('button.text');
    $buttonUrl = $buttonEnabled ? $block->ctaUrl('button') : null;
    $buttonNewTab = $block->ctaOpensNewTab('button');
    $hasButton = $buttonUrl !== null && strip_tags($buttonText) !== '';

    $gridCols = match ($columns) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
@endphp

@if ($records->isNotEmpty())
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            @if (strip_tags($heading) !== '')
                <div class="mb-10 tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
            @endif

            @if ($layout === 'list')
                <div class="flex flex-col divide-y divide-black/10 dark:divide-white/10 lg:w-3/4">
                    @foreach ($records as $record)
                        <x-site.blocks.collection-item :record="$record" :show-image="$showImage" layout="list" wire:key="collection-{{ $block->id }}-{{ $record->id }}" />
                    @endforeach
                </div>
            @else
                <div class="grid grid-cols-1 gap-6 {{ $gridCols }}">
                    @foreach ($records as $record)
                        <x-site.blocks.collection-item :record="$record" :show-image="$showImage" layout="grid" wire:key="collection-{{ $block->id }}-{{ $record->id }}" />
                    @endforeach
                </div>
            @endif

            @if ($hasButton)
                <div class="mt-10 flex justify-center">
                    <a
                        href="{{ $buttonUrl }}"
                        @if ($buttonNewTab) target="_blank" rel="noopener noreferrer" @endif
                        class="inline-flex items-center justify-center rounded-(--wire-radius) border px-6 py-2.5 text-sm font-medium transition hover:opacity-80"
                        style="border-color:var(--wire-primary-bg);color:var(--wire-primary-bg)"
                    >{{ strip_tags($buttonText) }}</a>
                </div>
            @endif
        </div>
    </section>
@endif
