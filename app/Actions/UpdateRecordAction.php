<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Services\ActivityLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRecordAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Record $record, array $attributes): void
    {
        DB::transaction(function () use ($record, $attributes): void {
            $record->recordBaselineRevision();
            $previousStatus = $record->computed_status;

            $redirects = new RedirectMovedContentAction();
            $previousPaths = isset($attributes['slugs']) ? $redirects->livePaths($record) : [];

            $record->update([
                ...Arr::except($attributes, ['slugs', 'status', 'blocks', 'media', 'categories']),
                ...$this->handlePublication($record, $attributes),
                'updated_at' => now(),
            ]);

            if (isset($attributes['slugs'])) {
                $record->updateSlugs($attributes['slugs']);
                $redirects->handle($record, $previousPaths);
            }

            if (isset($attributes['blocks'])) {
                $record->updateBlocks($attributes['blocks']);
            }

            if (isset($attributes['categories'])) {
                $record->categories()->sync($attributes['categories']);
            }

            if (is_array($attributes['media'] ?? null)) {
                $this->syncMedia($record, $attributes['media']);
            }

            ActivityLog::contentSaved($record, $previousStatus, $record->recordRevision());
        });

        if ($record->recordType->admin_only) {
            new MakeDocumentsPrivateAction()->handle($record);
        }
    }

    /**
     * @param  array<string, array<string, array<int, array<string, mixed>>>>  $media
     */
    private function syncMedia(Record $record, array $media): void
    {
        foreach ($media as $role => $localizedItems) {
            foreach ($localizedItems as $locale => $items) {
                $record->syncMediaForRole($role, $locale, $items);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function handlePublication(Record $record, array $attributes): array
    {
        return match ($attributes['status'] ?? 'draft') {
            ContentStatus::PUBLISHED => ['status' => ContentStatus::PUBLISHED->value, 'published_at' => $record->status === ContentStatus::PUBLISHED && $record->published_at?->isPast() ? $record->published_at : now()],
            ContentStatus::SCHEDULED => ['status' => ContentStatus::PUBLISHED->value, 'published_at' => $attributes['published_at']],
            ContentStatus::PRIVATE => ['status' => ContentStatus::PRIVATE->value, 'published_at' => null],
            default => ['status' => ContentStatus::DRAFT->value, 'published_at' => null],
        };
    }
}
