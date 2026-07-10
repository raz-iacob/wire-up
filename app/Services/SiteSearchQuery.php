<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Database\Eloquent\Builder;

final class SiteSearchQuery
{
    /**
     * @param  array<int, int|string>  $sources
     * @return array<int, array{key: string, defaultLabel: string, total: int, results: array<int, array{title: string, excerpt: string, url: string, image: ?string}>}>
     */
    public function search(string $query, array $sources, int $perType): array
    {
        $query = mb_trim($query);

        if ($query === '' || $sources === []) {
            return [];
        }

        $perType = max(1, min(24, $perType));

        $typeIds = [];

        foreach ($sources as $source) {
            if ((string) $source !== 'pages' && is_numeric($source)) {
                $typeIds[] = (int) $source;
            }
        }

        $types = $typeIds === []
            ? collect()
            : RecordType::query()->whereIn('id', $typeIds)->get()->keyBy('id');

        $groups = [];

        foreach ($sources as $source) {
            if ((string) $source === 'pages') {
                $group = $this->pagesGroup($query, $perType);
            } else {
                $type = is_numeric($source) ? $types->get((int) $source) : null;
                $group = $type instanceof RecordType ? $this->recordGroup($type, $query, $perType) : null;
            }

            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @return array{key: string, defaultLabel: string, total: int, results: array<int, array{title: string, excerpt: string, url: string, image: ?string}>}|null
     */
    private function recordGroup(RecordType $type, string $query, int $perType): ?array
    {
        $base = Record::query()
            ->where('record_type_id', $type->id)
            ->publishedInLocale()
            ->matchingSearch($query, $type);

        $total = (clone $base)->count();

        if ($total === 0) {
            return null;
        }

        $results = $base
            ->with(['recordType', 'media', 'slugs', 'translations'])
            ->latest('published_at')
            ->limit($perType)
            ->get()
            ->map(fn (Record $record): array => [
                'title' => $record->displayHeading(),
                'excerpt' => $record->displayExcerpt(),
                'url' => $record->getUrl(),
                'image' => $record->primaryImageUrl(800),
            ])
            ->all();

        return ['key' => (string) $type->id, 'defaultLabel' => $type->name, 'total' => $total, 'results' => $results];
    }

    /**
     * @return array{key: string, defaultLabel: string, total: int, results: array<int, array{title: string, excerpt: string, url: string, image: ?string}>}|null
     */
    private function pagesGroup(string $query, int $perType): ?array
    {
        $base = Page::query()
            ->publishedInLocale()
            ->where(function (Builder $inner) use ($query): void {
                $inner->whereTranslationLike('title', $query)
                    ->orWhereTranslationLike('description', $query);
            });

        $total = (clone $base)->count();

        if ($total === 0) {
            return null;
        }

        $results = $base
            ->with(['slugs', 'translations'])
            ->latest('published_at')
            ->limit($perType)
            ->get()
            ->map(fn (Page $page): array => [
                'title' => $page->title,
                'excerpt' => $this->pageExcerpt($page),
                'url' => $page->getUrl(),
                'image' => null,
            ])
            ->all();

        return ['key' => 'pages', 'defaultLabel' => __('Pages'), 'total' => $total, 'results' => $results];
    }

    private function pageExcerpt(Page $page): string
    {
        $source = $page->description !== '' ? $page->description : $page->plainText();

        return str(html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->squish()
            ->limit(160)
            ->value();
    }
}
