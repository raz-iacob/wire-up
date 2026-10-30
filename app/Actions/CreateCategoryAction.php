<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\Category;
use App\Services\ActivityLog;
use Illuminate\Support\Facades\DB;

final readonly class CreateCategoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Category
    {
        return DB::transaction(function () use ($attributes): Category {
            $category = Category::query()->create($attributes);

            ActivityLog::record(ActivityEvent::CATEGORY_CREATED, $category);

            return $category;
        });
    }
}
