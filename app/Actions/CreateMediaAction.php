<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\Media;
use App\Services\ActivityLog;

final readonly class CreateMediaAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Media
    {
        $media = Media::query()->create($attributes);

        ActivityLog::record(ActivityEvent::MEDIA_UPLOADED, $media);

        return $media;
    }
}
