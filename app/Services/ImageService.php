<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

final class ImageService
{
    private ImageInterface $image;

    private static ?ImageManager $manager = null;

    /**
     * @var array{
     *   w?: int,
     *   h?: int,
     *   crop?: string, // 'w,h,x,y' or 'w-h-x-y'
     *   q?: int, // quality
     *   fm?: 'png'|'gif'|'webp'|'jpg' // format
     * }
     */
    private array $options = [];

    public static function make(string $fileKey): self
    {
        return (new self)->setSourceFile($fileKey);
    }

    public static function placeholder(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
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

        if ($width <= 0 || $height <= 0 || $offsetX < 0 || $offsetY < 0 || $width > 1920 || $height > 1920) {
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
        $this->options = $options;

        $crop = $this->parseCrop($options['crop'] ?? '');

        if ($crop !== null && $crop !== []) {
            $this->image->crop(...$crop);
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

    public function response(int $cacheAgeSeconds = 30 * 86400): Response
    {
        $quality = (int) ($this->options['q'] ?? 80);
        $format = $this->options['fm'] ?? 'jpg';
        [$mime, $encoder] = match (mb_strtolower($format)) {
            'png' => ['image/png', new PngEncoder],
            'gif' => ['image/gif', new GifEncoder],
            'webp' => ['image/webp', new WebpEncoder(quality: $quality)],
            default => ['image/jpeg', new JpegEncoder(quality: $quality)],
        };

        return response($this->image->encode($encoder)->toString(), 200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', "public, max-age={$cacheAgeSeconds}, s-maxage={$cacheAgeSeconds}, immutable");
    }

    private function manager(): ImageManager
    {
        return self::$manager ??= new ImageManager(new GdDriver);
    }
}
