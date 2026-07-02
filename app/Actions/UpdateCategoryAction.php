<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCategoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Category $category, array $attributes): void
    {
        DB::transaction(fn () => $category->update($attributes));
    }
}
