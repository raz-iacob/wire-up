@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];
    $columns = (int) ($content['columns'] ?? 3);
    $columns = in_array($columns, [2, 3, 4], true) ? $columns : 3;
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $cardStyle = (bool) ($content['cardStyle'] ?? true);
    $imageRounded = (bool) ($content['imageRounded'] ?? false);
    $imageHeightClass = match ($content['imageHeight'] ?? 'medium') {
        'icon' => 'max-h-12',
        'small' => 'max-h-16',
        'large' => 'max-h-40',
        'xl' => 'max-h-60',
        default => 'max-h-24',
    };

    $items = collect($rawItems)
        ->map(fn (mixed $item, int $i): array => [
            'image' => $block->imageUrl("items.{$i}.image", ['w' => 800]),
            'alt' => $block->imageAlt("items.{$i}.image") ?: $block->text("items.{$i}.title"),
            'title' => $block->text("items.{$i}.title"),
            'body' => $block->text("items.{$i}.body"),
            'cta' => [
                'enabled' => (bool) data_get($item, 'cta.enabled', false),
                'text' => $block->text("items.{$i}.cta.text"),
                'url' => $block->ctaUrl("items.{$i}.cta"),
                'newTab' => data_get($item, 'cta.link.type') === 'url' && (bool) data_get($item, 'cta.link.newTab'),
                'bg' => (data_get($item, 'cta.bg') ?: null) ?? 'var(--wire-primary-bg)',
                'fg' => (data_get($item, 'cta.textColor') ?: null) ?? 'var(--wire-primary-text)',
            ],
        ])
        ->filter(fn (array $item): bool => $item['title'] !== '' || strip_tags($item['body']) !== '' || $item['image'] !== null)
        ->values();

    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';

    $gridCols = match ($columns) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };

    $cardBg = ($content['cardBg'] ?? null) ?: ($hasBg ? 'var(--wire-body-bg)' : 'var(--wire-card-bg)');
    $cardText = ($content['cardText'] ?? null) ?: ($hasBg ? 'var(--wire-body-text)' : 'var(--wire-card-text)');
@endphp

<section @class([
    'w-full py-18',
    'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
])>
    <div class="mx-auto max-w-(--wire-container) px-6">
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

        @if ($items->isNotEmpty())
            <div class="grid grid-cols-1 gap-6 {{ $gridCols }}">
                @foreach ($items as $item)
                    <x-site.blocks.feature-card
                        :item="$item"
                        :image-height-class="$imageHeightClass"
                        :image-rounded="$imageRounded"
                        :card-style="$cardStyle"
                        :card-bg="$cardBg"
                        :card-text="$cardText"
                        wire:key="feature-card-{{ $loop->index }}" />
                @endforeach
            </div>
        @endif
    </div>
</section>
