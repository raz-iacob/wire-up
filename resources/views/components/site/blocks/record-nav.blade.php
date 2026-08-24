@props(['block'])

@php
    $content = $block->content ?? [];
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $showTitles = (bool) ($content['showTitles'] ?? true);
    $for = $page ?? null;

    $neighbours = $for instanceof \App\Models\Record
        ? resolve(\App\Services\RecordNeighbourQuery::class)->forRecord($for, (bool) ($content['sameCategory'] ?? false))
        : ['previous' => null, 'next' => null];

    $previousLabel = $block->plainTextField('previousLabel') ?: __('Previous');
    $nextLabel = $block->plainTextField('nextLabel') ?: __('Next');
@endphp

@if ($neighbours['previous'] || $neighbours['next'])
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <nav class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            <div class="grid gap-4 border-t border-(--wire-divider) pt-8 sm:grid-cols-2">
                @foreach (['previous', 'next'] as $direction)
                    @php($neighbour = $neighbours[$direction])
                    @if ($neighbour)
                        <a
                            href="{{ $neighbour->getUrl() }}"
                            wire:navigate
                            @class([
                                'group border-(--wire-card-border) flex flex-col gap-1 rounded-(--wire-radius) border p-4 transition hover:opacity-80',
                                'sm:col-start-2 sm:items-end sm:text-right' => $direction === 'next',
                            ])
                        >
                            <span class="flex items-center gap-1.5 text-sm opacity-70">
                                @if ($direction === 'previous')
                                    <flux:icon name="arrow-left" variant="micro" class="size-4 shrink-0" />
                                @endif
                                {{ $direction === 'previous' ? $previousLabel : $nextLabel }}
                                @if ($direction === 'next')
                                    <flux:icon name="arrow-right" variant="micro" class="size-4 shrink-0" />
                                @endif
                            </span>
                            @if ($showTitles)
                                <span class="font-medium">{{ $neighbour->displayHeading() }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </div>
        </nav>
    </section>
@endif
