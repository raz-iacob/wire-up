<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Media;

final readonly class CreateMediaAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Media
    {
        return Media::query()->create($attributes);
    }
}
