<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MediaType;
use App\Models\Media;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use RuntimeException;
use Throwable;

final readonly class StoreMediaFileAction
{
    public function __construct(private CreateMediaAction $createMedia) {}

    /**
     * @param  array{width?: ?int, height?: ?int, duration?: ?int, thumbnail?: ?string, alt_text?: ?string, mime_type?: ?string, metadata?: array<string, mixed>}  $metadata
     */
    public function handle(string $path, string $originalName, array $metadata = []): Media
    {
        $etag = (string) md5_file($path);
        $existing = Media::query()->where('etag', $etag)->first();

        if ($existing instanceof Media) {
            return $existing;
        }

        $originalExtension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $detectedMime = $metadata['mime_type'] ?? (string) mime_content_type($path);

        $isSvg = $originalExtension === 'svg';
        $isHeic = in_array($originalExtension, ['heic', 'heif'], true) || str_contains(mb_strtolower($detectedMime), 'hei');

        $extension = $isHeic ? 'jpg' : $originalExtension;
        $filename = Str::uuid()->toString().'_'.Str::slug($basename).'.'.$extension;
        $width = $metadata['width'] ?? null;
        $height = $metadata['height'] ?? null;

        if ($isSvg) {
            [$size, $mimeType] = $this->storeSanitizedSvg($path, $filename);
        } elseif ($isHeic) {
            [$size, $mimeType, $width, $height] = $this->storeConvertedHeic($path, $filename);
        } else {
            [$size, $mimeType] = $this->storeVerbatim($path, $filename, $detectedMime);

            if ($width === null && MediaType::fromMimeType($mimeType) === MediaType::IMAGE) {
                $dimensions = getimagesize($path);
                $width = $dimensions !== false ? $dimensions[0] : null;
                $height = $dimensions !== false ? $dimensions[1] : null;
            }
        }

        return $this->createMedia->handle([
            'type' => MediaType::fromMimeType($mimeType)->value,
            'source' => 'media/'.$filename,
            'etag' => $etag,
            'filename' => $basename.'.'.$extension,
            'alt_text' => $metadata['alt_text'] ?? $basename,
            'mime_type' => $mimeType,
            'thumbnail' => $this->storeThumbnail($metadata['thumbnail'] ?? null, $basename),
            'size' => $size,
            'duration' => $metadata['duration'] ?? null,
            'width' => $width,
            'height' => $height,
            'metadata' => $metadata['metadata'] ?? null,
        ]);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function storeSanitizedSvg(string $path, string $filename): array
    {
        $clean = new Sanitizer()->sanitize((string) file_get_contents($path));
        $clean = $clean === false ? '' : $clean;

        $this->disk()->put('media/'.$filename, $clean, 'public');

        return [mb_strlen($clean, '8bit'), 'image/svg+xml'];
    }

    /**
     * @return array{0: int, 1: string, 2: int, 3: int}
     */
    private function storeConvertedHeic(string $path, string $filename): array
    {
        try {
            $imagick = new Imagick($path);

            match ($imagick->getImageOrientation()) {
                Imagick::ORIENTATION_BOTTOMRIGHT => $imagick->rotateImage('#000', 180),
                Imagick::ORIENTATION_RIGHTTOP => $imagick->rotateImage('#000', 90),
                Imagick::ORIENTATION_LEFTBOTTOM => $imagick->rotateImage('#000', -90),
                default => null,
            };

            $imagick->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);

            $jpeg = $imagick->getImageBlob();
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            $imagick->clear();
            $imagick->destroy();
        } catch (Throwable $exception) {
            throw new RuntimeException('The image could not be converted. Upload it as a JPG or PNG instead.', $exception->getCode(), previous: $exception);
        }

        $this->disk()->put('media/'.$filename, $jpeg, 'public');

        return [mb_strlen($jpeg, '8bit'), 'image/jpeg', $width, $height];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function storeVerbatim(string $path, string $filename, string $mimeType): array
    {
        $this->disk()->putFileAs('media', new File($path), $filename, 'public');

        return [(int) filesize($path), $mimeType];
    }

    private function storeThumbnail(?string $thumbnail, string $basename): ?string
    {
        if ($thumbnail === null || preg_match('/^data:image\/([a-zA-Z0-9]+);base64,/', $thumbnail, $matches) !== 1) {
            return null;
        }

        $base64 = explode(',', $thumbnail, 2)[1] ?? '';

        if ($base64 === '') {
            return null;
        }

        $filename = 'media/'.Str::uuid()->toString().'_'.Str::slug($basename).'_thumb.'.$matches[1];

        $this->disk()->put($filename, (string) base64_decode($base64, true), 'public');

        return $filename;
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config()->string('filesystems.media'));
    }
}
