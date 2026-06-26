@props(['block'])

@php
    $content = $block->content ?? [];
    $align = $content['align'] ?? 'center';
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];

    $variantStyle = fn (string $variant): string => match ($variant) {
        'secondary' => 'background-color:var(--wire-secondary-bg);color:var(--wire-secondary-text)',
        'outline' => 'background-color:transparent;color:var(--wire-primary-bg);border:1px solid var(--wire-primary-bg)',
        default => 'background-color:var(--wire-primary-bg);color:var(--wire-primary-text)',
    };

    $buttons = collect($rawItems)
        ->map(fn (mixed $item, int $i): array => [
            'text' => $block->text("items.{$i}.text"),
            'url' => $block->ctaUrl("items.{$i}"),
            'newTab' => data_get($item, 'link.type') === 'url' && (bool) data_get($item, 'link.newTab'),
            'variant' => (string) data_get($item, 'variant', 'primary'),
        ])
        ->filter(fn (array $button): bool => $button['text'] !== '' && $button['url'] !== null)
        ->values();
@endphp

@if ($buttons->isNotEmpty())
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            <div @class([
                'flex flex-wrap gap-4',
                'justify-start' => $align === 'left',
                'justify-center' => $align === 'center',
                'justify-end' => $align === 'right',
            ])>
                @foreach ($buttons as $button)
                    <a
                        href="{{ $button['url'] }}"
                        @if ($button['newTab']) target="_blank" rel="noopener noreferrer" @endif
                        class="inline-flex items-center justify-center rounded-(--wire-radius) px-6 py-3 text-base font-medium transition hover:opacity-90"
                        style="{{ $variantStyle($button['variant']) }}"
                    >{{ $button['text'] }}</a>
                @endforeach
            </div>
        </div>
    </section>
@endif
