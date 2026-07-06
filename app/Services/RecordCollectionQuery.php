<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Record;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator as SimplePaginator;

final class RecordCollectionQuery
{
    /**
     * @param  array<string, mixed>  $content
     * @return Collection<int, Record>
     */
    public function resolve(array $content): Collection
    {
        $recordTypeId = $this->recordTypeId($content);

        if ($recordTypeId === null) {
            return new Collection;
        }

        if ($this->source($content) === 'manual') {
            return $this->manualCollection($recordTypeId, $content);
        }

        $limit = max(1, min(100, (int) ($content['limit'] ?? 12)));

        return $this->baseQuery($recordTypeId, $content)
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $content
     * @return LengthAwarePaginator<int, Record>
     */
    public function paginate(array $content, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, min(500, $perPage));
        $page = max(1, $page);
        $recordTypeId = $this->recordTypeId($content);

        if ($recordTypeId === null) {
            return Record::query()->whereRaw('1 = 0')->paginate($perPage, ['*'], 'page', $page);
        }

        if ($this->source($content) === 'manual') {
            $all = $this->manualCollection($recordTypeId, $content);

            return new LengthAwarePaginator(
                $all->forPage($page, $perPage)->values(),
                $all->count(),
                $perPage,
                $page,
                ['path' => SimplePaginator::resolveCurrentPath()],
            );
        }

        return $this->baseQuery($recordTypeId, $content)
            ->latest('published_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function recordTypeId(array $content): ?int
    {
        return is_numeric($content['recordTypeId'] ?? null) ? (int) $content['recordTypeId'] : null;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function source(array $content): string
    {
        return in_array($content['source'] ?? null, ['latest', 'manual', 'category'], true)
            ? $content['source']
            : 'latest';
    }

    /**
     * @param  array<string, mixed>  $content
     * @return Builder<Record>
     */
    private function baseQuery(int $recordTypeId, array $content): Builder
    {
        $query = Record::query()
            ->where('record_type_id', $recordTypeId)
            ->publishedInLocale()
            ->with(['recordType', 'media', 'slugs', 'translations']);

        if ($this->source($content) === 'category') {
            $categoryId = is_numeric($content['categoryId'] ?? null) ? (int) $content['categoryId'] : null;

            $categoryId === null
                ? $query->whereRaw('1 = 0')
                : $query->whereHas('categories', fn (BuilderContract $categories): BuilderContract => $categories->whereKey($categoryId));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return Collection<int, Record>
     */
    private function manualCollection(int $recordTypeId, array $content): Collection
    {
        $ids = array_values(array_filter(array_map(intval(...), (array) ($content['recordIds'] ?? []))));

        if ($ids === []) {
            return new Collection;
        }

        return Record::query()
            ->where('record_type_id', $recordTypeId)
            ->publishedInLocale()
            ->with(['recordType', 'media', 'slugs', 'translations'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Record $record): int => (int) array_search($record->id, $ids, true))
            ->values();
    }
}
