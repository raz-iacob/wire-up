<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCategoryAction
{
    public function handle(Category $category): void
    {
        DB::transaction(fn () => $category->delete());
    }
}
