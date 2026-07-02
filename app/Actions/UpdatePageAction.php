<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePageAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Page $page, array $attributes): void
    {
        DB::transaction(function () use ($page, $attributes): void {

            $page->update([
                ...Arr::except($attributes, ['slugs', 'status', 'og_image', 'blocks', 'categories']),
                ...$this->handlePublication($attributes),
            ]);

            if (isset($attributes['slugs'])) {
                $page->updateSlugs($attributes['slugs']);
            }

            if (isset($attributes['blocks'])) {
                $page->updateBlocks($attributes['blocks']);
            }

            if (isset($attributes['og_image'])) {
                $this->syncMedia($page, 'og_image', $attributes['og_image']);
            }

            if (isset($attributes['categories'])) {
                $page->categories()->sync($attributes['categories']);
            }

        });
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $localizedItems
     */
    private function syncMedia(Page $page, string $role, array $localizedItems): void
    {
        foreach ($localizedItems as $locale => $items) {
            $page->syncMediaForRole($role, $locale, $items);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function handlePublication(array $attributes): array
    {
        return match ($attributes['status'] ?? 'draft') {
            ContentStatus::PUBLISHED => ['status' => ContentStatus::PUBLISHED->value, 'published_at' => now()],
            ContentStatus::SCHEDULED => ['status' => ContentStatus::PUBLISHED->value, 'published_at' => $attributes['published_at']],
            ContentStatus::PRIVATE => ['status' => ContentStatus::PRIVATE->value, 'published_at' => null],
            default => ['status' => ContentStatus::DRAFT->value, 'published_at' => null],
        };
    }
}
