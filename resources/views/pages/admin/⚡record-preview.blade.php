<?php

declare(strict_types=1);

use App\Models\Block;
use App\Models\Media;
use App\Models\Mediable;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Translation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

return new class extends Component
{
    public Record $record;

    public string $recordTitle = '';

    public string $recordDescription = '';

    public function mount(RecordType $recordType, Record $record, string $token): void
    {
        abort_unless($record->record_type_id === $recordType->id, 404);

        /** @var array{record_id: int, locale: string, title: string, description: string, data: array<string, mixed>, blocks: array<int, array{type: string, content?: array<string, mixed>, position?: int}>, media: array<string, array<string, array<int, array<string, mixed>>>>}|null $snapshot */
        $snapshot = Cache::get("record-preview:{$record->id}:".auth()->id().":{$token}");

        abort_unless(is_array($snapshot) && $snapshot['record_id'] === $record->id, 404);

        app()->setLocale($snapshot['locale']);

        $this->recordTitle = $snapshot['title'];
        $this->recordDescription = $snapshot['description'];

        $record->setRelation('recordType', $recordType);
        $record->setAttribute('data', $snapshot['data']);

        $record->setRelation('translations', collect([
            new Translation(['key' => 'title', 'locale' => $snapshot['locale'], 'body' => $snapshot['title']]),
            new Translation(['key' => 'description', 'locale' => $snapshot['locale'], 'body' => $snapshot['description']]),
        ]));

        $record->setRelation('blocks', collect($snapshot['blocks'])
            ->map(fn (array $block): Block => new Block([
                'type' => $block['type'],
                'content' => $block['content'] ?? [],
                'position' => $block['position'] ?? 0,
            ]))
            ->values());

        $record->setRelation('media', $this->buildMedia($snapshot['media']));
        $record->setRelation('categories', collect());

        $this->record = $record;
    }

    /**
     * @param  array<string, array<string, array<int, array<string, mixed>>>>  $snapshot
     * @return EloquentCollection<int, Media>
     */
    private function buildMedia(array $snapshot): EloquentCollection
    {
        $ids = [];
        foreach ($snapshot as $localized) {
            foreach ($localized as $items) {
                foreach ($items as $item) {
                    if (isset($item['id'])) {
                        $ids[] = (int) $item['id'];
                    }
                }
            }
        }

        $models = Media::query()->whereIn('id', array_values(array_unique($ids)))->get()->keyBy('id');

        /** @var EloquentCollection<int, Media> $result */
        $result = new EloquentCollection;

        foreach ($snapshot as $role => $localized) {
            foreach ($localized as $locale => $items) {
                foreach (array_values($items) as $position => $item) {
                    $id = isset($item['id']) ? (int) $item['id'] : null;
                    $model = $id !== null ? $models->get($id) : null;

                    if (! $model instanceof Media) {
                        continue;
                    }

                    $media = clone $model;
                    $media->setRelation('pivot', new Mediable([
                        'role' => (string) $role,
                        'locale' => (string) $locale,
                        'position' => $position,
                        'crop' => is_array($item['crop'] ?? null) ? $item['crop'] : [],
                        'metadata' => is_array($item['metadata'] ?? null) ? $item['metadata'] : [],
                    ]));

                    $result->push($media);
                }
            }
        }

        return $result;
    }

    public function render(): View
    {
        return $this->view()
            ->layout('layouts.app')
            ->title($this->recordTitle !== '' ? $this->recordTitle : $this->record->recordType->name)
            ->layoutData([
                'description' => $this->recordDescription,
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
