<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\Revision;
use App\Services\MediaItem;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

trait HasRevisionHistory
{
    #[Url(as: 'restore', except: null)]
    public ?int $restoring = null;

    #[Locked]
    public ?string $restoredFrom = null;

    public bool $showHistory = false;

    abstract protected function revisionable(): Page|Record;

    abstract protected function revisionAbility(): string;

    abstract protected function revisionUrl(?int $revision): string;

    /**
     * @param  array<string, mixed>  $content
     */
    abstract protected function fillFromRevision(array $content): void;

    /**
     * @return Collection<int, Revision>
     */
    #[Computed]
    public function revisionHistory(): Collection
    {
        return $this->revisionable()->revisions()->with('user')->get();
    }

    public function openHistory(): void
    {
        $this->authorize($this->revisionAbility());

        $this->showHistory = true;

        Flux::modal('revision-history')->show();
    }

    public function restoreRevision(int $revision): void
    {
        $this->authorize($this->revisionAbility());

        $this->revisionable()->revisions()->whereKey($revision)->firstOrFail();

        $this->redirect($this->revisionUrl($revision));
    }

    protected function loadRequestedRevision(): void
    {
        $revision = $this->restoring === null
            ? null
            : $this->revisionable()->revisions()->whereKey($this->restoring)->first();

        if ($revision === null) {
            $this->restoring = null;

            return;
        }

        $this->fillFromRevision($revision->content);
        $this->restoredFrom = $revision->created_at->diffForHumans();
    }

    protected function forgetRestoredRevision(): void
    {
        $this->restoring = null;
        $this->restoredFrom = null;

        unset($this->revisionHistory);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, string>
     */
    protected function revisionTexts(array $content, string $key): array
    {
        $texts = is_array($content[$key] ?? null) ? $content[$key] : [];

        return array_map(strval(...), array_intersect_key($texts, $this->activeLocales));
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int|string, array{id: string, type: string, position: int, content: array<string, mixed>}>
     */
    protected function revisionBlocks(array $content): array
    {
        $existing = $this->revisionable()->blocks()->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $blocks = [];

        foreach (array_values(is_array($content['blocks'] ?? null) ? $content['blocks'] : []) as $position => $block) {
            $id = in_array((string) ($block['id'] ?? ''), $existing, true) ? (string) $block['id'] : 'new-'.Str::uuid();

            $blocks[$id] = [
                'id' => $id,
                'type' => (string) $block['type'],
                'position' => $position,
                'content' => is_array($block['content'] ?? null) ? $block['content'] : [],
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function revisionMedia(array $content, string $role): array
    {
        $rows = collect(is_array($content['media'] ?? null) ? $content['media'] : [])
            ->filter(fn (array $row): bool => $row['role'] === $role && array_key_exists((string) $row['locale'], $this->activeLocales));

        $media = Media::query()->whereKey($rows->pluck('media_id')->all())->get()->keyBy('id');

        $items = array_fill_keys(array_keys($this->activeLocales), []);

        foreach ($rows->sortBy('position') as $row) {
            $found = $media->get($row['media_id']);

            if ($found instanceof Media) {
                $items[(string) $row['locale']][] = [
                    ...MediaItem::fromMedia($found),
                    'crop' => $row['crop'] ?? [],
                    'metadata' => $row['metadata'] ?? [],
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int, string>
     */
    protected function revisionCategories(array $content): array
    {
        $ids = is_array($content['categories'] ?? null) ? $content['categories'] : [];

        return Category::query()->whereKey($ids)->pluck('id')->map(fn (int $id): string => (string) $id)->all();
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function revisionMetadata(array $content): array
    {
        $metadata = is_array($content['metadata'] ?? null) ? $content['metadata'] : [];
        $image = $metadata['layout']['backgroundImage'] ?? null;

        if (is_array($image) && isset($image['id']) && ! Media::query()->whereKey($image['id'])->exists()) {
            $metadata['layout']['backgroundImage'] = null;
        }

        return $metadata;
    }
}
