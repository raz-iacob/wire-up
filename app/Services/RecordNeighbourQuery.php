<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Record;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;

final class RecordNeighbourQuery
{
    /**
     * @return array{previous: ?Record, next: ?Record}
     */
    public function forRecord(Record $record, bool $sameCategory = false): array
    {
        if ($record->published_at === null) {
            return ['previous' => null, 'next' => null];
        }

        $categoryIds = $sameCategory ? $record->categories->pluck('id')->all() : [];

        return [
            'previous' => $this->neighbour($record, $categoryIds, '<', 'desc'),
            'next' => $this->neighbour($record, $categoryIds, '>', 'asc'),
        ];
    }

    /**
     * @param  array<int, mixed>  $categoryIds
     */
    private function neighbour(Record $record, array $categoryIds, string $operator, string $direction): ?Record
    {
        return Record::query()
            ->where('record_type_id', $record->record_type_id)
            ->whereKeyNot($record->getKey())
            ->publishedInLocale()
            ->with(['recordType', 'media', 'slugs', 'translations'])
            ->when(
                $categoryIds !== [],
                fn (Builder $query): Builder => $query->whereHas(
                    'categories',
                    fn (BuilderContract $categories): BuilderContract => $categories->whereIn('categories.id', $categoryIds),
                ),
            )
            ->where(fn (Builder $query): Builder => $query
                ->where('published_at', $operator, $record->published_at)
                ->orWhere(fn (Builder $tie): Builder => $tie
                    ->where('published_at', $record->published_at)
                    ->where('id', $operator, $record->getKey())))
            ->orderBy('published_at', $direction)
            ->orderBy('id', $direction)
            ->first();
    }
}
