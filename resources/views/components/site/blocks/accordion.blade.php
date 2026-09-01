@props(['block'])

@php
    $content = $block->content ?? [];
    $headingLevel = \App\Services\BlockHeading::level($content['headingLevel'] ?? null);
    $heading = $block->text('heading');
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];
    $iconMode = ($content['icon'] ?? 'chevron') === 'plus-minus' ? 'plus-minus' : 'chevron';
    $exclusive = (bool) ($content['exclusive'] ?? true);
    $hasBg = (bool) ($content['hasBackground'] ?? false);

    $items = collect($rawItems)
        ->map(fn (mixed $item, int|string $i): array => [
            'title' => $block->text("items.{$i}.title"),
            'body' => $block->text("items.{$i}.body"),
        ])
        ->filter(fn (array $item): bool => $item['title'] !== '' || strip_tags($item['body']) !== '')
        ->values();
@endphp

<section @class([
    'w-full',
    'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
    ($pad ?? 'py-16') => $hasBg,
])>
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
        <div class="max-w-3xl">
            @if ($heading)
                <div class="[&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline mb-8 text-(length:--wire-heading-size) tracking-tight text-(--wire-heading)">
                    <x-site.blocks.heading :html="$heading" :level="$headingLevel" />
                </div>
            @endif

            @if ($items->isNotEmpty())
                <flux:accordion class="site-accordion" data-icon="{{ $iconMode }}" :exclusive="$exclusive" transition>
                    @foreach ($items as $item)
                        <flux:accordion.item :expanded="$loop->first">
                            <flux:accordion.heading>{{ $item['title'] }}</flux:accordion.heading>
                            <flux:accordion.content>
                                <div class="wire-prose [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-2 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-(--wire-list-indent) [&_ol]:pl-(--wire-list-indent) leading-(--wire-body-leading) *:first:mt-0 *:last:mb-0">
                                    {!! $item['body'] !!}
                                </div>
                            </flux:accordion.content>
                        </flux:accordion.item>
                    @endforeach
                </flux:accordion>
            @endif
        </div>
    </div>
</section>
