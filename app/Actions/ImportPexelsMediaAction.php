<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class ImportPexelsMediaAction
{
    public function __construct(private CreateMediaAction $createMedia) {}

    /**
     * @param  array<string, mixed>  $item  Normalized Pexels result from PexelsService
     */
    public function handle(array $item): Media
    {
        $type = MediaType::tryFrom((string) ($item['type'] ?? '')) ?? MediaType::IMAGE;
        $pexelsId = (int) ($item['id'] ?? 0);

        $alreadyImported = Media::query()
            ->where('type', $type->value)
            ->where('metadata->source', 'pexels')
            ->where('metadata->pexels_id', $pexelsId)
            ->first();

        if ($alreadyImported instanceof Media) {
            return $alreadyImported;
        }

        $contents = Http::retry(2, 100)->get((string) $item['download_url'])->throw()->body();
        $etag = md5($contents);

        $existingByEtag = Media::query()->where('etag', $etag)->first();

        if ($existingByEtag instanceof Media) {
            return $existingByEtag;
        }

        $disk = Storage::disk(config('filesystems.media'));
        $uuid = Str::uuid()->toString();
        $photographer = (string) ($item['photographer'] ?? '');
        $slug = Str::slug(($photographer !== '' ? $photographer.'-' : '').'pexels-'.$pexelsId);
        $extension = (string) ($item['extension'] ?? 'jpg');
        $filename = $uuid.'_'.$slug.'.'.$extension;

        $disk->put("media/$filename", $contents, 'public');

        $thumbnail = null;

        if ($type === MediaType::VIDEO && ! empty($item['preview'])) {
            $thumbContents = Http::retry(2, 100)->get((string) $item['preview'])->body();

            if ($thumbContents !== '') {
                $thumbnail = "media/{$uuid}_{$slug}_thumb.jpg";
                $disk->put($thumbnail, $thumbContents, 'public');
            }
        }

        $altText = (string) ($item['alt'] ?? '');

        return $this->createMedia->handle([
            'type' => $type->value,
            'source' => "media/$filename",
            'etag' => $etag,
            'filename' => $slug.'.'.$extension,
            'alt_text' => $altText !== '' ? $altText : mb_trim('Photo by '.$photographer),
            'mime_type' => (string) ($item['mime_type'] ?? null),
            'thumbnail' => $thumbnail,
            'size' => mb_strlen($contents, '8bit'),
            'duration' => $item['duration'] ?? null,
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'metadata' => [
                'source' => 'pexels',
                'pexels_id' => $pexelsId,
                'photographer' => $photographer,
                'photographer_url' => (string) ($item['photographer_url'] ?? ''),
                'pexels_url' => (string) ($item['pexels_url'] ?? ''),
            ],
        ]);
    }
}
