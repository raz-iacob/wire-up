<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Models\Media;

final readonly class MediaPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Media $media): array
    {
        return [
            'id' => $media->id,
            'type' => $media->type->value,
            'source' => $media->source,
            'filename' => $media->filename,
            'alt_text' => $media->alt_text,
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
            'size' => $media->size,
        ];
    }
}
