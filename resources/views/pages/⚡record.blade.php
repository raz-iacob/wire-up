<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use Illuminate\View\View;
use Livewire\Component;

return new class extends Component
{
    public Record $record;

    public function mount(string $recordType, string $slug): void
    {
        $type = RecordType::query()->where('slug_prefix', $recordType)->firstOrFail();

        $this->record = Record::query()
            ->where('record_type_id', $type->id)
            ->with(['recordType', 'blocks', 'media', 'translations', 'slugs', 'categories'])
            ->forSlug($slug, null, $type->slug_prefix)
            ->publishedInLocale()
            ->firstOrFail();
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->record->title)
            ->layoutData([
                'description' => $this->record->description,
                'siteLayout' => Page::normalizeLayout([]),
                'page' => null,
            ]);
    }
};
?>

<div>
    @includeFirst([
        'components.site.records.'.$record->recordType->key,
        'components.site.records.default',
    ], ['record' => $record])

    <x-site.page-content :page="$record" />
</div>
