<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Record;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class RecordCollectionQuery
{
    /**
     * @param  array<string, mixed>  $content
     * @return Collection<int, Record>
     */
    public function resolve(array $content): Collection
    {
        $recordTypeId = is_numeric($content['recordTypeId'] ?? null) ? (int) $content['recordTypeId'] : null;

        if ($recordTypeId === null) {
            return new Collection;
        }

        $query = Record::query()
            ->where('record_type_id', $recordTypeId)
            ->publishedInLocale()
            ->with(['recordType', 'media', 'slugs', 'translations']);

        $source = in_array($content['source'] ?? null, ['latest', 'manual', 'category'], true)
            ? $content['source']
            : 'latest';

        if ($source === 'manual') {
            $ids = array_values(array_filter(array_map(intval(...), (array) ($content['recordIds'] ?? []))));

            if ($ids === []) {
                return new Collection;
            }

            return $query->whereIn('id', $ids)->get()
                ->sortBy(fn (Record $record): int => (int) array_search($record->id, $ids, true))
                ->values();
        }

        if ($source === 'category') {
            $categoryId = is_numeric($content['categoryId'] ?? null) ? (int) $content['categoryId'] : null;

            if ($categoryId === null) {
                return new Collection;
            }

            $query->whereHas('categories', fn (Builder $categories): Builder => $categories->whereKey($categoryId));
        }

        $limit = max(1, min(100, (int) ($content['limit'] ?? 12)));

        return $query->latest('published_at')->limit($limit)->get();
    }
}
