<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\RevisionSource;
use App\Models\Media;
use App\Models\Record;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;

trait HasRevisions
{
    public const int REVISIONS_KEPT = 50;

    /**
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('id');
    }

    public function recordRevision(): void
    {
        $this->storeRevision(
            auth()->id(),
            RevisionSource::tryFrom((string) Context::get(RevisionSource::CONTEXT_KEY)) ?? RevisionSource::EDITOR,
        );
    }

    public function recordBaselineRevision(): void
    {
        if ($this->wasRecentlyCreated || $this->revisions()->exists()) {
            return;
        }

        $this->storeRevision($this->updated_by, RevisionSource::EDITOR, $this->updated_at);
    }

    /**
     * @return array<string, mixed>
     */
    public function revisionSnapshot(): array
    {
        $this->load(['translations', 'slugs', 'blocks', 'media', 'categories']);

        return self::normalizeSnapshot([
            'title' => $this->translationsFor('title'),
            'description' => $this->translationsFor('description'),
            'slugs' => $this->getSlugsArray(),
            'blocks' => array_values(array_map(
                fn (array $block): array => ['id' => $block['id'], 'type' => $block['type'], 'content' => $block['content']],
                $this->getBlocksArray(),
            )),
            'media' => $this->media
                ->map(fn (Media $media): array => [
                    'role' => (string) $media->pivot->role,
                    'locale' => (string) $media->pivot->locale,
                    'position' => (int) $media->pivot->position,
                    'media_id' => $media->id,
                    'crop' => $media->pivot->crop,
                    'metadata' => $media->pivot->metadata,
                ])
                ->sortBy([['role', 'asc'], ['locale', 'asc'], ['position', 'asc']])
                ->values()
                ->all(),
            'categories' => $this->categories->pluck('id')->sort()->values()->all(),
            'metadata' => Arr::except($this->metadata ?? [], ['published_locales']),
            ...($this instanceof Record ? ['data' => $this->data ?? []] : []),
        ]);
    }

    protected static function bootHasRevisions(): void
    {
        static::deleting(function (self $model): void {
            $model->revisions()->delete();
        });
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function normalizeSnapshot(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? self::normalizeSnapshot($item) : $item, $value);
    }

    private function storeRevision(?int $userId, RevisionSource $source, mixed $createdAt = null): void
    {
        $content = $this->revisionSnapshot();
        $hash = hash('sha256', json_encode($content, JSON_THROW_ON_ERROR));

        if ($this->revisions()->value('hash') === $hash) {
            return;
        }

        $this->revisions()->create([
            'user_id' => $userId,
            'source' => $source,
            'hash' => $hash,
            'content' => $content,
            'created_at' => $createdAt ?? now(),
        ]);

        $kept = $this->revisions()->limit(self::REVISIONS_KEPT)->pluck('id');

        Revision::query()
            ->where('revisionable_type', $this->getMorphClass())
            ->where('revisionable_id', $this->getKey())
            ->whereNotIn('id', $kept)
            ->delete();
    }
}
