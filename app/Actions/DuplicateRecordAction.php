<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContentStatus;
use App\Models\Record;
use Illuminate\Support\Facades\DB;

final readonly class DuplicateRecordAction
{
    public function handle(Record $record, ?string $title = null): Record
    {
        return DB::transaction(function () use ($record, $title): Record {
            $record->loadMissing(['blocks', 'media', 'categories', 'translations', 'recordType']);

            $titles = collect($record->translationsFor('title'))
                ->map(fn (string $body): string => $body !== '' ? 'Copy of '.$body : $body)
                ->all();

            if ($title !== null) {
                $titles[app()->getLocale()] = $title;
            }

            $copy = Record::query()->create([
                'record_type_id' => $record->record_type_id,
                'title' => $titles,
                'description' => $record->translationsFor('description'),
                'data' => $record->data,
                'metadata' => $record->metadata,
                'status' => ContentStatus::DRAFT,
                'published_at' => null,
            ]);

            foreach ($record->blocks as $block) {
                $copy->blocks()->create([
                    'type' => $block->type->value,
                    'position' => $block->position,
                    'content' => $block->content ?? [],
                ]);
            }

            foreach ($record->media as $media) {
                $copy->media()->attach($media->id, [
                    'role' => $media->pivot->role,
                    'locale' => $media->pivot->locale,
                    'position' => $media->pivot->position,
                    'crop' => $media->pivot->crop,
                    'metadata' => $media->pivot->metadata,
                ]);
            }

            $copy->categories()->sync($record->categories->pluck('id')->all());

            $copy->setRelation('recordType', $record->recordType);
            $copy->load('translations');
            $copy->setSlugs();

            return $copy;
        });
    }
}
