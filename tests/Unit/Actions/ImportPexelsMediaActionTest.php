<?php

declare(strict_types=1);

use App\Actions\ImportPexelsMediaAction;
use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('filesystems.media'));
});

function pexelsPhotoItem(array $overrides = []): array
{
    return array_merge([
        'id' => 123,
        'type' => MediaType::IMAGE->value,
        'thumb' => 'https://images.pexels.com/photos/123/medium.jpeg',
        'preview' => 'https://images.pexels.com/photos/123/large.jpeg',
        'width' => 4000,
        'height' => 3000,
        'duration' => null,
        'photographer' => 'Jane Doe',
        'photographer_url' => 'https://www.pexels.com/@jane',
        'pexels_url' => 'https://www.pexels.com/photo/123',
        'alt' => 'A cat on a wall',
        'avg_color' => '#112233',
        'download_url' => 'https://images.pexels.com/photos/123/pexels-photo-123.jpeg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpeg',
    ], $overrides);
}

it('downloads a pexels photo and stores it as a media record with attribution', function (): void {
    Http::fake([
        'images.pexels.com/*' => Http::response('binary-image-bytes', 200),
    ]);

    $media = resolve(ImportPexelsMediaAction::class)->handle(pexelsPhotoItem());

    expect($media->type)->toBe(MediaType::IMAGE)
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->alt_text)->toBe('A cat on a wall')
        ->and($media->width)->toBe(4000)
        ->and($media->height)->toBe(3000)
        ->and($media->metadata['source'])->toBe('pexels')
        ->and($media->metadata['pexels_id'])->toBe(123)
        ->and($media->metadata['photographer'])->toBe('Jane Doe')
        ->and($media->metadata['photographer_url'])->toBe('https://www.pexels.com/@jane')
        ->and($media->metadata['pexels_url'])->toBe('https://www.pexels.com/photo/123');

    Storage::disk(config('filesystems.media'))->assertExists($media->source);
});

it('does not re-download a pexels photo that was already imported', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response('binary-image-bytes', 200)]);

    $first = resolve(ImportPexelsMediaAction::class)->handle(pexelsPhotoItem());
    $second = resolve(ImportPexelsMediaAction::class)->handle(pexelsPhotoItem());

    expect($second->id)->toBe($first->id)
        ->and(Media::query()->count())->toBe(1);
});

it('reuses an existing media record when the downloaded bytes match an etag', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response('shared-bytes', 200)]);

    $existing = Media::factory()->create(['etag' => md5('shared-bytes')]);

    $media = resolve(ImportPexelsMediaAction::class)->handle(pexelsPhotoItem(['id' => 999]));

    expect($media->id)->toBe($existing->id)
        ->and(Media::query()->count())->toBe(1);
});

it('downloads a pexels video together with its thumbnail', function (): void {
    Http::fake([
        'videos.pexels.com/*' => Http::response('binary-video-bytes', 200),
        'images.pexels.com/*' => Http::response('binary-thumb-bytes', 200),
    ]);

    $media = resolve(ImportPexelsMediaAction::class)->handle(pexelsPhotoItem([
        'id' => 555,
        'type' => MediaType::VIDEO->value,
        'duration' => 42,
        'preview' => 'https://images.pexels.com/videos/555/thumb.jpg',
        'download_url' => 'https://videos.pexels.com/555/hd.mp4',
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
        'alt' => '',
    ]));

    expect($media->type)->toBe(MediaType::VIDEO)
        ->and($media->mime_type)->toBe('video/mp4')
        ->and($media->duration)->toBe(42)
        ->and($media->thumbnail)->not->toBeNull()
        ->and($media->alt_text)->toBe('Photo by Jane Doe');

    $disk = Storage::disk(config('filesystems.media'));
    $disk->assertExists($media->source);
    $disk->assertExists($media->thumbnail);
});
