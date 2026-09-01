<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use WithPagination;

    public ?Page $page = null;

    public ?RecordType $recordType = null;

    public bool $unpublished = false;

    public function mount(string $slug): void
    {
        $staff = auth()->user()?->canAccessAdmin() || request()->hasValidSignature();

        $query = Page::query()->with('blocks')->forSlug($slug);
        $page = $staff ? $query->first() : $query->publishedInLocale()->first();

        if ($page === null) {
            $this->recordType = RecordType::query()->where('slug_prefix', $slug)->where('has_index_page', true)->first();

            abort_if($this->recordType === null, 404);

            return;
        }

        $this->page = $page;
        $this->unpublished = $staff && ! $page->isLiveInLocale();

        if (! $staff && $page->isMembersOnly() && auth()->guest()) {
            session()->put('url.intended', url()->full());
            $this->redirect(route('login'));
        }
    }

    /**
     * @return LengthAwarePaginator<int, Record>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return Record::query()
            ->where('record_type_id', $this->recordType?->id)
            ->with(['recordType', 'media', 'slugs', 'translations'])
            ->publishedInLocale()
            ->latest('published_at')
            ->paginate(12);
    }

    public function render(): View
    {
        if ($this->recordType instanceof RecordType) {
            return $this->view()
                ->title($this->recordType->name)
                ->layoutData(['description' => '', 'siteLayout' => [], 'page' => null]);
        }

        return $this->view()
            ->title($this->page->title)
            ->layoutData([
                'description' => $this->page->description,
                'siteLayout' => $this->page->resolvedLayout(),
                'page' => $this->page,
            ]);
    }
};
?>

<div>
    @if ($recordType)
        <section class="w-full py-16">
            <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
                <h1 class="mb-10 text-(length:--wire-heading-size) tracking-tight text-(--wire-heading)">
                    {{ $recordType->name }}
                </h1>

                @if ($this->records->isNotEmpty())
                    <x-site.blocks.collection-records
                        :records="$this->records"
                        :block-id="'index-'.$recordType->id"
                        layout="grid"
                        :columns="3"
                    />

                    @if ($this->records->hasPages())
                        <div class="mt-10">
                            <flux:pagination :paginator="$this->records" />
                        </div>
                    @endif
                @endif
            </div>
        </section>
    @else
        <x-site.page-content :page="$page" />

        @if ($unpublished)
            <x-site.unpublished-notice :message="__('This page is not published')" />
        @endif
    @endif
</div>
