<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasCategories
{
    /**
     * @return MorphToMany<Category, $this>
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    protected static function bootHasCategories(): void
    {
        static::deleted(fn (self $model) => $model->categories()->detach());
    }
}
