<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class PexelsService
{
    private const string PHOTO_BASE = 'https://api.pexels.com/v1';

    private const string VIDEO_BASE = 'https://api.pexels.com/videos';

    public function configured(): bool
    {
        return filled(config('services.pexels.key'));
    }

    /**
     * @return array{results: array<int, array<string, mixed>>, hasMore: bool}
     */
    public function searchPhotos(string $query, int $page = 1, int $perPage = 24): array
    {
        $query = mb_trim($query);
        $endpoint = $query === '' ? self::PHOTO_BASE.'/curated' : self::PHOTO_BASE.'/search';

        $response = $this->request($endpoint, array_filter([
            'query' => $query === '' ? null : $query,
            'page' => $page,
            'per_page' => $perPage,
        ]));

        return [
            'results' => array_values(array_map(
                $this->normalizePhoto(...),
                $response['photos'] ?? [],
            )),
            'hasMore' => ! empty($response['next_page']),
        ];
    }

    /**
     * @return array{results: array<int, array<string, mixed>>, hasMore: bool}
     */
    public function searchVideos(string $query, int $page = 1, int $perPage = 24): array
    {
        $query = mb_trim($query);
        $endpoint = $query === '' ? self::VIDEO_BASE.'/popular' : self::VIDEO_BASE.'/search';

        $response = $this->request($endpoint, array_filter([
            'query' => $query === '' ? null : $query,
            'page' => $page,
            'per_page' => $perPage,
        ]));

        return [
            'results' => array_values(array_map(
                $this->normalizeVideo(...),
                $response['videos'] ?? [],
            )),
            'hasMore' => ! empty($response['next_page']),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $url, array $params): array
    {
        return Cache::remember(
            'pexels:'.$url.'?'.http_build_query($params),
            now()->addMinutes(30),
            fn (): array => Http::withHeaders(['Authorization' => (string) config('services.pexels.key')])
                ->get($url, $params)
                ->throw()
                ->json() ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $photo
     * @return array<string, mixed>
     */
    private function normalizePhoto(array $photo): array
    {
        $original = (string) ($photo['src']['original'] ?? '');

        return [
            'id' => (int) ($photo['id'] ?? 0),
            'type' => MediaType::IMAGE->value,
            'thumb' => (string) ($photo['src']['medium'] ?? $photo['src']['tiny'] ?? $original),
            'preview' => (string) ($photo['src']['large'] ?? $original),
            'width' => (int) ($photo['width'] ?? 0),
            'height' => (int) ($photo['height'] ?? 0),
            'duration' => null,
            'photographer' => (string) ($photo['photographer'] ?? ''),
            'photographer_url' => (string) ($photo['photographer_url'] ?? ''),
            'pexels_url' => (string) ($photo['url'] ?? ''),
            'alt' => (string) ($photo['alt'] ?? ''),
            'avg_color' => (string) ($photo['avg_color'] ?? ''),
            'download_url' => $original,
            'mime_type' => $this->mimeFromUrl($original, 'image/jpeg'),
            'extension' => $this->extensionFromUrl($original, 'jpg'),
        ];
    }

    /**
     * @param  array<string, mixed>  $video
     * @return array<string, mixed>
     */
    private function normalizeVideo(array $video): array
    {
        /** @var array<int, array<string, mixed>> $files */
        $files = $video['video_files'] ?? [];

        $best = collect($files)
            ->filter(fn (array $file): bool => ($file['file_type'] ?? '') === 'video/mp4' && ! empty($file['link']))
            ->sortByDesc(fn (array $file): int => (int) ($file['width'] ?? 0))
            ->first() ?? ($files[0] ?? []);

        $thumb = (string) ($video['image'] ?? $video['video_pictures'][0]['picture'] ?? '');

        return [
            'id' => (int) ($video['id'] ?? 0),
            'type' => MediaType::VIDEO->value,
            'thumb' => $thumb,
            'preview' => $thumb,
            'width' => (int) ($best['width'] ?? $video['width'] ?? 0),
            'height' => (int) ($best['height'] ?? $video['height'] ?? 0),
            'duration' => (int) ($video['duration'] ?? 0),
            'photographer' => (string) ($video['user']['name'] ?? ''),
            'photographer_url' => (string) ($video['user']['url'] ?? ''),
            'pexels_url' => (string) ($video['url'] ?? ''),
            'alt' => '',
            'avg_color' => '',
            'download_url' => (string) ($best['link'] ?? ''),
            'mime_type' => (string) ($best['file_type'] ?? 'video/mp4'),
            'extension' => 'mp4',
        ];
    }

    private function extensionFromUrl(string $url, string $fallback): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === '' ? $fallback : $extension;
    }

    private function mimeFromUrl(string $url, string $fallback): string
    {
        return match ($this->extensionFromUrl($url, '')) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => $fallback,
        };
    }
}
