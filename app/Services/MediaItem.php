<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;

final class MediaItem
{
    /**
     * @return array<string, mixed>
     */
    public static function fromMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'source' => $media->source,
            'preview' => $media->preview,
            'crop_src' => $media->cropSrc,
            'filename' => $media->filename,
            'alt_text' => $media->alt_text,
            'mime_type' => $media->mime_type,
            'thumbnail' => $media->thumbnail,
            'icon' => $media->type->icon(),
            'size' => $media->size,
            'duration' => $media->duration,
            'width' => $media->width,
            'height' => $media->height,
            'dimensions' => $media->dimensions,
            'created_at' => $media->created_at->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fromSource(string $source): ?array
    {
        if ($source === '') {
            return null;
        }

        $media = Media::query()->where('source', $source)->first();

        return $media instanceof Media ? self::fromMedia($media) : null;
    }
}
