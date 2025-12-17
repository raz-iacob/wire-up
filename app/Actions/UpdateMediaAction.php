<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Media;

final readonly class UpdateMediaAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Media $media, array $attributes): void
    {
        $media->update($attributes);
    }
}
