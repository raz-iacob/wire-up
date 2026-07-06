<?php

declare(strict_types=1);

use App\Services\RecordCollectionQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use WithPagination;

    public string $blockId = '';

    public string $mode = 'paged';

    public string $pad = 'py-16';

    /**
     * @var array<string, mixed>
     */
    public array $content = [];

    public string $heading = '';

    public ?string $buttonUrl = null;

    public string $buttonText = '';

    public bool $buttonNewTab = false;

    public int $visiblePages = 1;

    /**
     * @return LengthAwarePaginator<int, \App\Models\Record>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        $perPage = max(1, min(48, (int) ($this->content['perPage'] ?? 6)));

        return resolve(RecordCollectionQuery::class)->paginate(
            $this->content,
            $this->mode === 'infinite' ? $perPage * $this->visiblePages : $perPage,
            $this->mode === 'infinite' ? 1 : $this->getPage(),
        );
    }

    public function loadMore(): void
    {
        $this->visiblePages++;
    }

    public function render(): View
    {
        return $this->view();
    }
};
?>

<div>
    @php
        $records = $this->records();

        $hasBg = (bool) ($content['hasBackground'] ?? false);
        $layout = in_array($content['layout'] ?? 'grid', ['grid', 'list'], true) ? $content['layout'] : 'grid';
        $columns = (int) ($content['columns'] ?? 3);
        $showImage = (bool) ($content['showImage'] ?? true);
        $hasHeading = strip_tags($heading) !== '';

        $fieldKeys = array_values(array_filter((array) ($content['fields'] ?? []), 'is_string'));
        $displayFields = $records->getCollection()->first()?->recordType?->pickFields($fieldKeys) ?? [];

        $hasButton = $buttonUrl !== null && strip_tags($buttonText) !== '';
    @endphp

    @if ($records->isNotEmpty())
        <section @class([
            'w-full',
            'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
            $pad => $hasBg,
        ])>
            <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
                @if ($hasHeading)
                    <div class="mb-10 tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                @endif

                <div wire:loading.class="opacity-60" wire:target="loadMore, gotoPage, nextPage, previousPage">
                    <x-site.blocks.collection-records
                        :records="$records"
                        :layout="$layout"
                        :columns="$columns"
                        :show-image="$showImage"
                        :fields="$displayFields"
                        :block-id="$blockId" />
                </div>

                @if ($mode === 'paged')
                    @if ($records->hasPages())
                        <div class="mt-10">
                            <flux:pagination :paginator="$records" />
                        </div>
                    @endif
                @elseif ($records->hasMorePages())
                    <div class="mt-10 flex justify-center">
                        <flux:button variant="filled" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                            {{ __('Load more') }}
                        </flux:button>
                    </div>
                    <div wire:intersect="loadMore" class="h-px"></div>
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
</div>
