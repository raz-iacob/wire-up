<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Services\PexelsService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.pexels.key', 'test-key');
});

it('reports configured only when an api key is present', function (): void {
    config()->set('services.pexels.key');
    expect(resolve(PexelsService::class)->configured())->toBeFalse();

    config()->set('services.pexels.key', 'abc');
    expect(resolve(PexelsService::class)->configured())->toBeTrue();
});

it('sends the api key in the authorization header', function (): void {
    Http::fake(['api.pexels.com/*' => Http::response(['photos' => [], 'next_page' => null])]);

    resolve(PexelsService::class)->searchPhotos('cats');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'test-key'));
});

it('searches photos and normalizes the response', function (): void {
    Http::fake(['api.pexels.com/v1/search*' => Http::response([
        'photos' => [[
            'id' => 123,
            'width' => 4000,
            'height' => 3000,
            'url' => 'https://www.pexels.com/photo/123',
            'photographer' => 'Jane Doe',
            'photographer_url' => 'https://www.pexels.com/@jane',
            'avg_color' => '#112233',
            'alt' => 'A cat',
            'src' => [
                'original' => 'https://images.pexels.com/photos/123/pexels-photo-123.jpeg',
                'large' => 'https://images.pexels.com/photos/123/large.jpeg',
                'medium' => 'https://images.pexels.com/photos/123/medium.jpeg',
                'tiny' => 'https://images.pexels.com/photos/123/tiny.jpeg',
            ],
        ]],
        'next_page' => 'https://api.pexels.com/v1/search?page=2',
    ])]);

    $result = resolve(PexelsService::class)->searchPhotos('cats');

    expect($result['hasMore'])->toBeTrue()
        ->and($result['results'])->toHaveCount(1);

    $photo = $result['results'][0];

    expect($photo['id'])->toBe(123)
        ->and($photo['type'])->toBe(MediaType::IMAGE->value)
        ->and($photo['thumb'])->toBe('https://images.pexels.com/photos/123/medium.jpeg')
        ->and($photo['download_url'])->toBe('https://images.pexels.com/photos/123/pexels-photo-123.jpeg')
        ->and($photo['mime_type'])->toBe('image/jpeg')
        ->and($photo['extension'])->toBe('jpeg')
        ->and($photo['photographer'])->toBe('Jane Doe')
        ->and($photo['photographer_url'])->toBe('https://www.pexels.com/@jane')
        ->and($photo['pexels_url'])->toBe('https://www.pexels.com/photo/123');
});

it('maps the photo mime type from the original file extension', function (string $query, string $url, string $mime, string $extension): void {
    Http::fake(['api.pexels.com/v1/search*' => Http::response([
        'photos' => [[
            'id' => 1,
            'width' => 10,
            'height' => 10,
            'url' => 'https://www.pexels.com/photo/1',
            'photographer' => 'A',
            'photographer_url' => 'u',
            'alt' => '',
            'src' => ['original' => $url],
        ]],
        'next_page' => null,
    ])]);

    $photo = resolve(PexelsService::class)->searchPhotos($query)['results'][0];

    expect($photo['mime_type'])->toBe($mime)
        ->and($photo['extension'])->toBe($extension);
})->with([
    'png' => ['pngs', 'https://images.pexels.com/1.png', 'image/png', 'png'],
    'gif' => ['gifs', 'https://images.pexels.com/1.gif', 'image/gif', 'gif'],
    'webp' => ['webps', 'https://images.pexels.com/1.webp', 'image/webp', 'webp'],
    'unknown falls back to jpeg' => ['bmps', 'https://images.pexels.com/1.bmp', 'image/jpeg', 'bmp'],
]);

it('falls back to the curated endpoint when the query is empty', function (): void {
    Http::fake([
        'api.pexels.com/v1/curated*' => Http::response(['photos' => [], 'next_page' => null]),
        'api.pexels.com/v1/search*' => Http::response(['photos' => [], 'next_page' => null]),
    ]);

    resolve(PexelsService::class)->searchPhotos('');

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/v1/curated'));
    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/v1/search'));
});

it('searches videos and picks the highest resolution mp4 file', function (): void {
    Http::fake(['api.pexels.com/videos/search*' => Http::response([
        'videos' => [[
            'id' => 555,
            'width' => 1920,
            'height' => 1080,
            'duration' => 42,
            'url' => 'https://www.pexels.com/video/555',
            'image' => 'https://images.pexels.com/videos/555/thumb.jpg',
            'user' => ['name' => 'Sam Shooter', 'url' => 'https://www.pexels.com/@sam'],
            'video_files' => [
                ['quality' => 'sd', 'file_type' => 'video/mp4', 'width' => 640, 'height' => 360, 'link' => 'https://videos.pexels.com/555/sd.mp4'],
                ['quality' => 'hd', 'file_type' => 'video/mp4', 'width' => 1920, 'height' => 1080, 'link' => 'https://videos.pexels.com/555/hd.mp4'],
            ],
        ]],
        'next_page' => null,
    ])]);

    $result = resolve(PexelsService::class)->searchVideos('ocean');
    $video = $result['results'][0];

    expect($video['type'])->toBe(MediaType::VIDEO->value)
        ->and($video['download_url'])->toBe('https://videos.pexels.com/555/hd.mp4')
        ->and($video['width'])->toBe(1920)
        ->and($video['duration'])->toBe(42)
        ->and($video['thumb'])->toBe('https://images.pexels.com/videos/555/thumb.jpg')
        ->and($video['photographer'])->toBe('Sam Shooter')
        ->and($video['mime_type'])->toBe('video/mp4')
        ->and($video['extension'])->toBe('mp4');
});

it('uses the popular videos endpoint when the query is empty', function (): void {
    Http::fake([
        'api.pexels.com/videos/popular*' => Http::response(['videos' => [], 'next_page' => null]),
        'api.pexels.com/videos/search*' => Http::response(['videos' => [], 'next_page' => null]),
    ]);

    resolve(PexelsService::class)->searchVideos('');

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/videos/popular'));
    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/videos/search'));
});
