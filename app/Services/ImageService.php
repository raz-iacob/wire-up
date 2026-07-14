<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class ImageService
{
    private ImageInterface $image;

    private static ?ImageManager $manager = null;

    /**
     * @var array{
     *   w?: int,
     *   h?: int,
     *   crop?: string,
     *   q?: int,
     *   fm?: 'png'|'gif'|'webp'|'jpg'
     * }
     */
    private array $options = [];

    public static function make(string $fileKey): self
    {
        return (new self)->setSourceFile($fileKey);
    }

    public static function url(string $options, string $path): string
    {
        return url(URL::signedRoute('image.show', ['options' => $options, 'path' => $path], absolute: false));
    }

    public static function cached(string $options, string $path): ?BinaryFileResponse
    {
        $file = self::cacheFile($options, $path);

        if (! is_file($file)) {
            return null;
        }

        touch($file);

        return self::fileResponse($file, self::formatFromOptions($options));
    }

    public static function transform(string $options, string $path): BinaryFileResponse
    {
        [$contents] = self::make($path)->applyOptionsString($options)->encoded();

        $file = self::cacheFile($options, $path);

        File::ensureDirectoryExists(dirname($file));
        File::put($file, $contents);

        return self::fileResponse($file, self::formatFromOptions($options));
    }

    public static function purgeCache(string $path): void
    {
        File::deleteDirectory(self::cacheRoot().'/'.hash('sha256', $path));
    }

    public static function pruneCache(int $maxMegabytes): int
    {
        $root = self::cacheRoot();

        if (! File::isDirectory($root)) {
            return 0;
        }

        $files = collect(File::allFiles($root))
            ->sortBy(fn (SplFileInfo $file): int => $file->getMTime())
            ->values();

        $total = $files->sum(fn (SplFileInfo $file): int => $file->getSize());
        $budget = $maxMegabytes * 1024 * 1024;
        $deleted = 0;

        foreach ($files as $file) {
            if ($total <= $budget) {
                break;
            }

            $total -= $file->getSize();
            File::delete($file->getPathname());
            $deleted++;
        }

        return $deleted;
    }

    public static function placeholder(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
    }

    public static function svg(string $fileKey, int $cacheAgeSeconds = 30 * 86400): Response
    {
        $disk = Storage::disk(config('filesystems.media'));

        abort_unless($disk->exists($fileKey), 404);

        return response((string) $disk->get($fileKey), 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox")
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', "public, max-age={$cacheAgeSeconds}, s-maxage={$cacheAgeSeconds}, immutable");
    }

    /**
     * @throws HttpResponseException
     */
    public function setSourceFile(string $fileKey): self
    {
        $stream = Storage::disk(config('filesystems.media'))->readStream($fileKey);

        abort_if(empty($stream), 404);

        $this->image = $this->manager()->read($stream);

        return $this;
    }

    public function applyOptionsString(string $optionsString): self
    {
        $options = collect(explode(',', $optionsString))
            ->mapWithKeys(function (string $opt): array {
                try {
                    $parts = explode('=', $opt, 2);

                    return [$parts[0] => $parts[1]];
                } catch (Throwable) {
                    return [];
                }
            })
            ->all();

        return $this->applyOptions($options);
    }

    /**
     * @return ?array<string, int>
     */
    public function parseCrop(string $crop): ?array
    {
        $parts = preg_split('/[,-]/', $crop);

        if (count($parts) < 4) {
            return null;
        }

        [$width, $height, $offsetX, $offsetY] = array_map(intval(...), array_slice($parts, 0, 4));

        if ($width <= 0 || $height <= 0 || $offsetX < 0 || $offsetY < 0) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'offset_x' => $offsetX,
            'offset_y' => $offsetY,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function applyOptions(array $options): self
    {
        $this->options = $this->normalizeOptions($options);

        $crop = $this->parseCrop($options['crop'] ?? '');

        if ($crop !== null && $crop !== []) {
            $imageWidth = $this->image->width();
            $imageHeight = $this->image->height();

            $width = min($crop['width'], $imageWidth);
            $height = min($crop['height'], $imageHeight);

            $this->image->crop(
                width: $width,
                height: $height,
                offset_x: min($crop['offset_x'], max(0, $imageWidth - $width)),
                offset_y: min($crop['offset_y'], max(0, $imageHeight - $height)),
            );
        }

        if (Arr::hasAny($options, ['w', 'h'])) {
            $w = isset($options['w']) ? min((int) $options['w'], 1920) : null;
            $h = isset($options['h']) ? min((int) $options['h'], 1920) : null;

            $this->image->scale(
                width: $w && $w < $this->image->width() ? $w : null,
                height: $h && $h < $this->image->height() ? $h : null,
            );
        }

        return $this;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function encoded(): array
    {
        $quality = (int) ($this->options['q'] ?? 80);
        $format = $this->options['fm'] ?? 'jpg';
        [$mime, $encoder] = match (mb_strtolower($format)) {
            'png' => ['image/png', new PngEncoder],
            'gif' => ['image/gif', new GifEncoder],
            'webp' => ['image/webp', new WebpEncoder(quality: $quality)],
            default => ['image/jpeg', new JpegEncoder(quality: $quality)],
        };

        return [$this->image->encode($encoder)->toString(), $mime];
    }

    private static function cacheRoot(): string
    {
        return storage_path('framework/images');
    }

    private static function cacheFile(string $options, string $path): string
    {
        return self::cacheRoot().'/'.hash('sha256', $path).'/'.hash('sha256', $options).'.'.self::formatFromOptions($options);
    }

    private static function formatFromOptions(string $options): string
    {
        return preg_match('/(?:^|,)fm=(png|gif|webp)(?:,|$)/', $options, $matches) === 1 ? $matches[1] : 'jpg';
    }

    private static function fileResponse(string $file, string $format, int $cacheAgeSeconds = 30 * 86400): BinaryFileResponse
    {
        $mime = match ($format) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return response()->file($file, [
            'Content-Type' => $mime,
            'Cache-Control' => "public, max-age={$cacheAgeSeconds}, s-maxage={$cacheAgeSeconds}, immutable",
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{w?: int, h?: int, crop?: string, q?: int, fm?: 'png'|'gif'|'webp'|'jpg'}
     */
    private function normalizeOptions(array $options): array
    {
        $normalized = [];

        if (isset($options['w'])) {
            $normalized['w'] = (int) $options['w'];
        }

        if (isset($options['h'])) {
            $normalized['h'] = (int) $options['h'];
        }

        if (isset($options['crop'])) {
            $normalized['crop'] = (string) $options['crop'];
        }

        if (isset($options['q'])) {
            $normalized['q'] = (int) $options['q'];
        }

        if (in_array($options['fm'] ?? null, ['png', 'gif', 'webp', 'jpg'], true)) {
            $normalized['fm'] = $options['fm'];
        }

        return $normalized;
    }

    private function manager(): ImageManager
    {
        return self::$manager ??= new ImageManager(new GdDriver);
    }
}
