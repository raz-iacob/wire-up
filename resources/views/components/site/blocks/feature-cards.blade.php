@props(['block'])

@php
    $content = $block->content ?? [];
    $headingLevel = \App\Services\BlockHeading::level($content['headingLevel'] ?? null);
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
    $iconSizeClass = match ($content['imageHeight'] ?? 'medium') {
        'icon' => 'size-8',
        'small' => 'size-10',
        'large' => 'size-20',
        'xl' => 'size-28',
        default => 'size-14',
    };
    $allowedIcons = config()->array('menu.icons');

    $items = collect($rawItems)
        ->map(fn (mixed $item, int|string $i): array => [
            'media' => data_get($item, 'media') === 'icon' ? 'icon' : 'image',
            'icon' => in_array(data_get($item, 'icon'), $allowedIcons, true) ? (string) data_get($item, 'icon') : '',
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
        ->filter(fn (array $item): bool => $item['title'] !== '' || strip_tags($item['body']) !== '' || $item['image'] !== null || $item['icon'] !== '')
        ->values();

    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';

    $gridCols = match ($columns) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };

    $cardBg = ($content['cardBg'] ?? null) ?: ($hasBg ? 'var(--wire-body-bg)' : 'var(--wire-card-bg)');
    $cardText = ($content['cardText'] ?? null) ?: ($hasBg ? 'var(--wire-body-text)' : 'var(--wire-card-text)');

    $layout = in_array($content['layout'] ?? 'grid', ['grid', 'list', 'carousel'], true) ? ($content['layout'] ?? 'grid') : 'grid';

    $buttonEnabled = (bool) data_get($content, 'button.enabled', false);
    $buttonText = $block->text('button.text');
    $buttonUrl = $buttonEnabled ? $block->ctaUrl('button') : null;
    $buttonNewTab = $block->ctaOpensNewTab('button');
    $hasButton = $buttonUrl !== null && strip_tags($buttonText) !== '';
@endphp

<section @class([
    'w-full',
    'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
    ($pad ?? 'py-16') => $hasBg,
])>
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
        @if ($hasHeading)
            <div class="mb-12">
                @if (strip_tags($heading) !== '')
                    <div class="[&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size) tracking-tight text-(--wire-heading)">
                        <x-site.blocks.heading :html="$heading" :level="$headingLevel" />
                    </div>
                @endif
                @if (strip_tags($intro) !== '')
                    <div class="[&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-2 mt-3 leading-(--wire-body-leading) opacity-80 *:first:mt-0 *:last:mb-0">
                        {!! $intro !!}
                    </div>
                @endif
            </div>
        @endif

        @if ($items->isNotEmpty())
            @if ($layout === 'carousel')
                <div
                    x-data="{
                        atStart: true,
                        atEnd: false,
                        scroll(dir) {
                            const t = this.$refs.track;
                            t.scrollBy({ left: dir * t.clientWidth * 0.8, behavior: 'smooth' });
                        },
                        update() {
                            const t = this.$refs.track;
                            this.atStart = t.scrollLeft <= 1;
                            this.atEnd = Math.ceil(t.scrollLeft + t.offsetWidth) >= t.scrollWidth;
                        },
                    }"
                    x-init="$nextTick(() => update())"
                >
                    @if ($items->count() > 1)
                        <div class="mb-6 hidden justify-end gap-2 sm:flex">
                            <flux:button
                                square
                                variant="subtle"
                                icon="chevron-left"
                                x-on:click="scroll(-1)"
                                x-bind:disabled="atStart"
                                class="disabled:opacity-40"
                                :aria-label="__('Previous')"
                            />
                            <flux:button
                                square
                                variant="subtle"
                                icon="chevron-right"
                                x-on:click="scroll(1)"
                                x-bind:disabled="atEnd"
                                class="disabled:opacity-40"
                                :aria-label="__('Next')"
                            />
                        </div>
                    @endif

                    <div
                        x-ref="track"
                        x-on:scroll="update()"
                        class="[&::-webkit-scrollbar]:hidden flex snap-x snap-mandatory scrollbar-none items-stretch gap-6 overflow-x-auto scroll-smooth pb-2"
                    >
                        @foreach ($items as $item)
                            <div
                                class="w-[80vw] shrink-0 snap-start sm:w-72 lg:w-80"
                                wire:key="feature-card-{{ $loop->index }}"
                            >
                                <x-site.blocks.feature-card
                                    :item="$item"
                                    :image-height-class="$imageHeightClass"
                                    :icon-size-class="$iconSizeClass"
                                    :image-rounded="$imageRounded"
                                    :card-style="$cardStyle"
                                    :card-bg="$cardBg"
                                    :card-text="$cardText"
                                />
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div @class([
                    'grid grid-cols-1 gap-6',
                    $gridCols => $layout === 'grid',
                    'lg:w-3/4' => $layout === 'list',
                ])>
                    @foreach ($items as $item)
                        <x-site.blocks.feature-card
                            :item="$item"
                            :image-height-class="$imageHeightClass"
                            :icon-size-class="$iconSizeClass"
                            :image-rounded="$imageRounded"
                            :card-style="$cardStyle"
                            :card-bg="$cardBg"
                            :card-text="$cardText"
                            wire:key="feature-card-{{ $loop->index }}"
                        />
                    @endforeach
                </div>
            @endif
        @endif

        @if ($hasButton)
            <div class="mt-10 flex justify-center">
                <a
                    href="{{ $buttonUrl }}"
                    @if ($buttonNewTab) target="_blank" rel="noopener noreferrer" @endif
                    class="inline-flex items-center justify-center rounded-(--wire-btn-radius) border px-6 py-2.5 text-sm font-medium transition hover:opacity-80"
                    style="border-color: var(--wire-primary-bg); color: var(--wire-primary-bg)"
                >{{ strip_tags($buttonText) }}</a>
            </div>
        @endif
    </div>
</section>
