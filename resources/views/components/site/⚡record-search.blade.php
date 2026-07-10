<?php

declare(strict_types=1);

use App\Services\SiteSearchQuery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

return new class extends Component
{
    #[Locked]
    public string $blockId = '';

    #[Locked]
    public string $pad = 'py-16';

    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $content = [];

    #[Locked]
    public string $heading = '';

    #[Locked]
    public string $placeholder = '';

    #[Url(as: 'search', except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->search = $this->term();
    }

    public function term(): string
    {
        return mb_substr(mb_trim($this->search), 0, 100);
    }

    /**
     * @return array<int, array{key: string, label: string, total: int, results: array<int, array{title: string, excerpt: string, url: string, image: ?string}>}>
     */
    #[Computed]
    public function groups(): array
    {
        $term = $this->term();

        if ($term === '') {
            return [];
        }

        $sources = array_values(array_filter((array) ($this->content['sources'] ?? []), is_scalar(...)));
        $perType = max(1, min(24, (int) ($this->content['perType'] ?? 4)));

        $labels = is_array($this->content['labels'] ?? null) ? $this->content['labels'] : [];
        $locale = app()->getLocale();

        return array_map(function (array $group) use ($labels, $locale): array {
            $custom = is_array($labels[$group['key']] ?? null) ? $labels[$group['key']] : [];
            $label = is_string($custom[$locale] ?? null) && mb_trim($custom[$locale]) !== '' ? $custom[$locale] : $group['defaultLabel'];

            return ['key' => $group['key'], 'label' => $label, 'total' => $group['total'], 'results' => $group['results']];
        }, resolve(SiteSearchQuery::class)->search($term, $sources, $perType));
    }

    public function render(): View
    {
        return $this->view();
    }
};
?>

<div>
    @php
        $hasBg = (bool) ($content['hasBackground'] ?? false);
        $layout = in_array($content['layout'] ?? 'grid', ['grid', 'list'], true) ? $content['layout'] : 'grid';
        $columns = (int) ($content['columns'] ?? 3);
        $showImage = (bool) ($content['showImage'] ?? true);
        $hasHeading = strip_tags($heading) !== '';
        $term = $this->term();
        $groups = $this->groups();
    @endphp

    <section @class([
        'w-full',
        $pad,
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            @if ($hasHeading)
                <div class="mb-8 text-center tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
            @endif

            <form wire:submit="$refresh" class="mx-auto flex max-w-xl items-center gap-3">
                <flux:input
                    wire:model="search"
                    type="search"
                    class="flex-1"
                    :placeholder="$placeholder !== '' ? $placeholder : __('Search')"
                    :aria-label="$placeholder !== '' ? $placeholder : __('Search')" />
                <flux:button type="submit" variant="primary" icon="magnifying-glass" square :aria-label="__('Search')" wire:loading.attr="disabled" />
            </form>

            @if ($term !== '')
                @if ($groups === [])
                    <flux:text class="mt-10 text-center">{{ __('No results for “:query”.', ['query' => $term]) }}</flux:text>
                @else
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-sm font-medium">
                        @foreach ($groups as $group)
                            <a href="#search-{{ $blockId }}-{{ $group['key'] }}" class="transition hover:text-(--wire-accent)">{{ number_format($group['total']) }} {{ $group['label'] }}</a>
                            @unless ($loop->last)
                                <span class="text-zinc-400 dark:text-zinc-500" aria-hidden="true">&bull;</span>
                            @endunless
                        @endforeach
                    </div>

                    @php($gridCols = match ($columns) { 2 => 'sm:grid-cols-2', 4 => 'sm:grid-cols-2 lg:grid-cols-4', default => 'sm:grid-cols-2 lg:grid-cols-3' })

                    <div class="mt-12 space-y-14">
                        @foreach ($groups as $group)
                            <div wire:key="search-group-{{ $blockId }}-{{ $group['key'] }}" id="search-{{ $blockId }}-{{ $group['key'] }}" class="scroll-mt-24">
                                <div class="mb-6 flex items-center gap-4">
                                    <flux:heading size="sm" class="uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $group['label'] }}</flux:heading>
                                    <div class="h-px flex-1 bg-black/10 dark:bg-white/10"></div>
                                </div>

                                <div @class(['flex flex-col divide-y divide-black/10 dark:divide-white/10 lg:w-3/4' => $layout === 'list', 'grid grid-cols-1 gap-6 '.$gridCols => $layout !== 'list'])>
                                    @foreach ($group['results'] as $i => $result)
                                        <x-site.blocks.search-result
                                            :title="$result['title']"
                                            :excerpt="$result['excerpt']"
                                            :url="$result['url']"
                                            :image="$showImage ? $result['image'] : null"
                                            :layout="$layout"
                                            wire:key="search-result-{{ $blockId }}-{{ $group['key'] }}-{{ $i }}" />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </section>
</div>
