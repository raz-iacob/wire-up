<?php

declare(strict_types=1);

use App\Models\Record;
use App\Models\RecordType;
use Illuminate\View\View;
use Livewire\Component;

return new class extends Component
{
    public Record $record;

    public bool $unpublished = false;

    public bool $unreachable = false;

    public function mount(string $recordType, string $slug): void
    {
        $type = RecordType::query()->where('slug_prefix', $recordType)->firstOrFail();

        $staff = auth()->user()?->canAccessAdmin() || request()->hasValidSignature();

        abort_if(! $type->has_detail_page && ! $staff, 404);

        $this->unreachable = ! $type->has_detail_page;

        $query = Record::query()
            ->where('record_type_id', $type->id)
            ->with(['recordType', 'blocks', 'media', 'translations', 'slugs', 'categories'])
            ->forSlug($slug, null, $type->slug_prefix);

        if ($staff) {
            $this->record = $query->firstOrFail();
            $this->unpublished = ! $this->record->isLiveInLocale();
        } else {
            $this->record = $query->publishedInLocale()->firstOrFail();

            if ($this->record->isMembersOnly() && auth()->guest()) {
                session()->put('url.intended', url()->full());
                $this->redirect(route('login'));
            }
        }
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->record->title)
            ->layoutData([
                'description' => $this->record->description,
                'siteLayout' => $this->record->resolvedLayout(),
                'page' => $this->record,
            ]);
    }
};
?>

<div data-record="{{ $record->recordType->key }}">
    @if ($record->recordType->breadcrumbs)
        <div class="mx-auto w-full max-w-(--wire-container) px-(--wire-gutter) pt-10">
            <x-site.breadcrumbs :trail="\App\Services\BreadcrumbService::current()->trail($record)" />
        </div>
    @endif

    @includeFirst([
        'components.site.records.'.$record->recordType->key,
        'components.site.records.default',
    ], ['record' => $record])

    @php
        $shop = resolve(\App\Services\ShopService::class);
    @endphp
    @if ($record->recordType->key !== 'product' && $shop->isPurchasable($record))
        <div class="mx-auto w-full max-w-(--wire-container) px-(--wire-gutter) pb-10">
            <livewire:site.buy-box :record="$record" :key="'buy-box-'.$record->id" />
        </div>
    @endif

    <x-site.page-content :page="$record" />

    @if ($unreachable)
        <x-site.unpublished-notice :message="__('This content type has no detail pages, so visitors cannot reach this')" />
    @elseif ($unpublished)
        <x-site.unpublished-notice :message="__('This record is not published')" />
    @endif
</div>
