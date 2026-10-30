<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\Category;
use App\Services\ActivityLog;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCategoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Category $category, array $attributes): void
    {
        DB::transaction(function () use ($category, $attributes): void {
            $previousNames = $category->translationsFor('name');

            $category->update($attributes);

            if ($category->load('translations')->translationsFor('name') !== $previousNames) {
                ActivityLog::record(ActivityEvent::CATEGORY_UPDATED, $category);
            }
        });
    }
}
