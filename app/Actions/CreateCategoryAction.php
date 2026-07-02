<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

final readonly class CreateCategoryAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Category
    {
        return DB::transaction(fn (): Category => Category::query()->create($attributes));
    }
}
