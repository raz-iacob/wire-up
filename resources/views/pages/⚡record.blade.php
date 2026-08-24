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

    public function mount(string $recordType, string $slug): void
    {
        $type = RecordType::query()->where('slug_prefix', $recordType)->firstOrFail();

        $query = Record::query()
            ->where('record_type_id', $type->id)
            ->with(['recordType', 'blocks', 'media', 'translations', 'slugs', 'categories'])
            ->forSlug($slug, null, $type->slug_prefix);

        if (auth()->user()?->canAccessAdmin() || request()->hasValidSignature()) {
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

    <x-site.page-content :page="$record" />

    @if ($unpublished)
        <x-site.unpublished-notice :message="__('This record is not published')" />
    @endif
</div>
