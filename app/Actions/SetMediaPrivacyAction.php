<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\MediaType;
use App\Models\Media;
use App\Services\ActivityLog;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

final readonly class SetMediaPrivacyAction
{
    public function handle(Media $media, bool $private): void
    {
        if ((bool) $media->is_private === $private || ($private && $media->type !== MediaType::DOCUMENT)) {
            return;
        }

        $from = $media->disk();
        $to = Storage::disk(config()->string($private ? 'filesystems.private_media' : 'filesystems.media'));

        foreach (array_filter([$media->source, $media->thumbnail]) as $path) {
            $stream = $from->exists($path) ? $from->readStream($path) : null;

            if (is_resource($stream)) {
                $to->writeStream($path, $stream, $private ? [] : ['visibility' => 'public']);
                $from->delete($path);
            }

            ImageService::purgeCache($path);
        }

        $media->forceFill(['is_private' => $private])->save();

        ActivityLog::record($private ? ActivityEvent::MEDIA_MADE_PRIVATE : ActivityEvent::MEDIA_MADE_PUBLIC, $media);
    }
}
