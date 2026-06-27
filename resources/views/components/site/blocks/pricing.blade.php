@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $columns = (int) ($content['columns'] ?? 3);
    $columns = in_array($columns, [2, 3, 4], true) ? $columns : 3;
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];

    $plans = collect($rawItems)
        ->map(fn (mixed $item, int $i): array => [
            'name' => $block->text("items.{$i}.name"),
            'price' => $block->text("items.{$i}.price"),
            'period' => $block->text("items.{$i}.period"),
            'description' => $block->text("items.{$i}.description"),
            'features' => $block->text("items.{$i}.features"),
            'featured' => (bool) data_get($item, 'featured', false),
            'badge' => $block->text("items.{$i}.badge"),
            'cta' => [
                'enabled' => (bool) data_get($item, 'cta.enabled', false),
                'text' => $block->text("items.{$i}.cta.text"),
                'url' => $block->ctaUrl("items.{$i}.cta"),
                'newTab' => data_get($item, 'cta.link.type') === 'url' && (bool) data_get($item, 'cta.link.newTab'),
                'bg' => (data_get($item, 'cta.bg') ?: null) ?? 'var(--wire-primary-bg)',
                'fg' => (data_get($item, 'cta.textColor') ?: null) ?? 'var(--wire-primary-text)',
            ],
        ])
        ->filter(fn (array $plan): bool => $plan['name'] !== '' || $plan['price'] !== '')
        ->values();

    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';

    $gridCols = match ($columns) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };

@endphp

@if ($plans->isNotEmpty())
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            @if ($hasHeading)
                <div class="mb-12">
                    @if (strip_tags($heading) !== '')
                        <div class="tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                    @endif
                    @if (strip_tags($intro) !== '')
                        <div class="mt-3 leading-relaxed opacity-80 [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 {{ $gridCols }} items-stretch">
                @foreach ($plans as $plan)
                    <article
                        @class([
                            'wire-card relative flex h-full flex-col gap-5 rounded-(--wire-radius) p-6 shadow-sm',
                            'bg-(--wire-body-bg) text-(--wire-body-text)' => $hasBg,
                            'bg-(--wire-card-bg) text-(--wire-card-text)' => ! $hasBg,
                            'ring-2 ring-(--wire-accent)' => $plan['featured'],
                        ])
                        wire:key="plan-{{ $loop->index }}"
                    >
                        @if ($plan['featured'] && $plan['badge'] !== '')
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-(--wire-accent) px-3 py-1 text-xs font-semibold text-(--wire-primary-text)">{{ $plan['badge'] }}</span>
                        @endif

                        @if ($plan['name'] !== '')
                            <h3 class="text-lg font-semibold tracking-tight">{{ $plan['name'] }}</h3>
                        @endif

                        @if ($plan['price'] !== '')
                            <div class="flex items-baseline gap-1">
                                <span class="text-4xl font-bold tracking-tight">{{ $plan['price'] }}</span>
                                @if ($plan['period'] !== '')
                                    <span class="text-sm opacity-70">{{ $plan['period'] }}</span>
                                @endif
                            </div>
                        @endif

                        @if ($plan['description'] !== '')
                            <p class="leading-relaxed opacity-80">{{ $plan['description'] }}</p>
                        @endif

                        @if (strip_tags($plan['features']) !== '')
                            <div class="grow leading-relaxed opacity-80 [&_a]:text-(--wire-accent) [&_a]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:my-1 [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $plan['features'] !!}</div>
                        @endif

                        @if ($plan['cta']['enabled'] && $plan['cta']['text'] !== '' && $plan['cta']['url'] !== null)
                            <a
                                href="{{ $plan['cta']['url'] }}"
                                @if ($plan['cta']['newTab']) target="_blank" rel="noopener noreferrer" @endif
                                class="wire-btn mt-auto inline-flex items-center justify-center rounded-(--wire-radius) px-6 py-3 text-base font-medium transition hover:opacity-90"
                                style="background-color:{{ $plan['cta']['bg'] }};color:{{ $plan['cta']['fg'] }};--wire-btn-border:var(--wire-primary-border)"
                            >{{ $plan['cta']['text'] }}</a>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
