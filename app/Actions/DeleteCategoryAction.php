<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\Category;
use App\Services\ActivityLog;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCategoryAction
{
    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $category->delete();

            ActivityLog::record(ActivityEvent::CATEGORY_DELETED, $category);
        });
    }
}
