<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PageStatus;
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
                ...Arr::except($attributes, ['slugs', 'status']),
                ...$this->handlePublication($attributes),
            ]);

            if (isset($attributes['slugs'])) {
                $page->updateSlugs($attributes['slugs']);
            }

            // $page->updateBlocks($attributes['blocks']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function handlePublication(array $attributes): array
    {
        return match ($attributes['status'] ?? 'draft') {
            PageStatus::PUBLISHED => ['status' => PageStatus::PUBLISHED->value, 'published_at' => now()],
            PageStatus::SCHEDULED => ['status' => PageStatus::PUBLISHED->value, 'published_at' => $attributes['published_at']],
            PageStatus::PRIVATE => ['status' => PageStatus::PRIVATE->value, 'published_at' => null],
            default => ['status' => PageStatus::DRAFT->value, 'published_at' => null],
        };
    }
}
