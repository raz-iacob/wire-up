@props(['block'])

@php
    $content = $block->content ?? [];
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $layout = in_array($content['layout'] ?? 'grid', ['grid', 'list', 'carousel'], true) ? $content['layout'] : 'grid';
    $columns = (int) ($content['columns'] ?? 3);
    $columns = in_array($columns, [2, 3, 4], true) ? $columns : 3;
    $showImage = (bool) ($content['showImage'] ?? true);
    $heading = $block->text('heading');

    $records = resolve(\App\Services\RecordCollectionQuery::class)->resolve($content);

    $fieldKeys = array_values(array_filter((array) ($content['fields'] ?? []), 'is_string'));
    $recordType = $records->first()?->recordType;
    $displayFields = [];

    if ($recordType !== null && $fieldKeys !== []) {
        foreach ($fieldKeys as $fieldKey) {
            $field = $recordType->fieldByKey($fieldKey);

            if ($field !== null && ! (\App\Enums\FieldType::tryFrom($field['type'] ?? '')?->isMedia() ?? false)) {
                $displayFields[] = $field;
            }
        }
    }

    $buttonEnabled = (bool) data_get($content, 'button.enabled', false);
    $buttonText = $block->text('button.text');
    $buttonUrl = $buttonEnabled ? $block->ctaUrl('button') : null;
    $buttonNewTab = $block->ctaOpensNewTab('button');
    $hasButton = $buttonUrl !== null && strip_tags($buttonText) !== '';
    $hasHeading = strip_tags($heading) !== '';

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
                @if ($hasHeading || $hasButton || $records->count() > 1)
                    <div class="mx-auto flex max-w-(--wire-container) flex-wrap items-center justify-between gap-4 px-(--wire-gutter)">
                        @if ($hasHeading)
                            <div class="tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                        @endif

                        <div class="flex items-center gap-3">
                            @if ($hasButton)
                                <a
                                    href="{{ $buttonUrl }}"
                                    @if ($buttonNewTab) target="_blank" rel="noopener noreferrer" @endif
                                    class="inline-flex items-center justify-center rounded-(--wire-radius) border px-6 py-2.5 text-sm font-medium transition hover:opacity-80"
                                    style="border-color:var(--wire-primary-bg);color:var(--wire-primary-bg)"
                                >{{ strip_tags($buttonText) }}</a>
                            @endif

                            @if ($records->count() > 1)
                                <div class="hidden gap-2 sm:flex">
                                    <flux:button square variant="subtle" icon="chevron-left" x-on:click="scroll(-1)" x-bind:disabled="atStart" class="disabled:opacity-40" :aria-label="__('Previous')" />
                                    <flux:button square variant="subtle" icon="chevron-right" x-on:click="scroll(1)" x-bind:disabled="atEnd" class="disabled:opacity-40" :aria-label="__('Next')" />
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <div
                    x-ref="track"
                    x-on:scroll="update()"
                    @class([
                        'flex items-stretch gap-6 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 scrollbar-none [&::-webkit-scrollbar]:hidden',
                        'px-[max(var(--wire-gutter),calc((100%-var(--wire-container))/2+var(--wire-gutter)))]',
                        'scroll-pl-[max(var(--wire-gutter),calc((100%-var(--wire-container))/2+var(--wire-gutter)))]',
                        'mt-8' => $hasHeading || $hasButton || $records->count() > 1,
                    ])
                >
                    @foreach ($records as $record)
                        <div class="w-[80vw] shrink-0 snap-start sm:w-72 lg:w-80" wire:key="collection-{{ $block->id }}-{{ $record->id }}">
                            <x-site.blocks.collection-item :record="$record" :show-image="$showImage" :fields="$displayFields" layout="grid" />
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
                @if ($hasHeading)
                    <div class="mb-10 tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                @endif

                @if ($layout === 'list')
                    <div class="flex flex-col divide-y divide-black/10 dark:divide-white/10 lg:w-3/4">
                        @foreach ($records as $record)
                            <x-site.blocks.collection-item :record="$record" :show-image="$showImage" :fields="$displayFields" layout="list" wire:key="collection-{{ $block->id }}-{{ $record->id }}" />
                        @endforeach
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-6 {{ $gridCols }}">
                        @foreach ($records as $record)
                            <x-site.blocks.collection-item :record="$record" :show-image="$showImage" :fields="$displayFields" layout="grid" wire:key="collection-{{ $block->id }}-{{ $record->id }}" />
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
        @endif
    </section>
@endif
