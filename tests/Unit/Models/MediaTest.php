<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Mediable;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

test('to array', function (): void {
    $media = Media::factory()->create()->fresh();

    expect(array_keys($media->toArray()))
        ->toEqual([
            'id',
            'type',
            'source',
            'etag',
            'filename',
            'alt_text',
            'mime_type',
            'thumbnail',
            'size',
            'duration',
            'width',
            'height',
            'created_at',
            'updated_at',
        ]);
});

it('has size cast to integer', function (): void {
    $media = Media::factory()->create([
        'size' => '1024',
    ])->fresh();

    expect($media->size)->toBeInt()
        ->and($media->size)->toBe(1024);
});

it('has width cast to integer', function (): void {
    $media = Media::factory()->create([
        'width' => '1920',
    ])->fresh();

    expect($media->width)->toBeInt()
        ->and($media->width)->toBe(1920);
});

it('has height cast to integer', function (): void {
    $media = Media::factory()->create([
        'height' => '1080',
    ])->fresh();

    expect($media->height)->toBeInt()
        ->and($media->height)->toBe(1080);
});

it('has type as MediaType enum', function (): void {
    $media = Media::factory()->create()->fresh();

    expect($media->type)->toBeInstanceOf(MediaType::class);
});

it('has many mediables', function (): void {
    $media = Media::factory()->create();
    $mediable1 = Mediable::factory()->create(['media_id' => $media->id]);
    $mediable2 = Mediable::factory()->create(['media_id' => $media->id]);

    expect($media->mediables)->toHaveCount(2)
        ->and($media->mediables->first()->id)->toBe($mediable1->id)
        ->and($media->mediables->last()->id)->toBe($mediable2->id);
});

it('returns temporary url for photo type with expires parameter', function (): void {
    Storage::fake();
    $media = Media::factory()->create([
        'type' => MediaType::PHOTO,
        'source' => 'photos/test-image.jpg',
    ])->fresh();

    $url = $media->url;

    expect($url)->toBeString()
        ->and($url)->toContain('photos/test-image.jpg')
        ->and($url)->toContain('expiration=');
});

it('returns photo thumbnail URL for photo media type', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::PHOTO,
        'source' => 'photos/test-image.jpg',
    ])->fresh();

    $expectedThumbnailUrl = route('image.show', ['w=300,h=300', 'photos/test-image.jpg']);

    expect($media->thumbnail)->toBe($expectedThumbnailUrl);
});

it('returns video thumbnail URL for video media type', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::VIDEO,
        'filename' => 'dQw4w9WgXcQ',
        'source' => 'videos/test-video.mp4',
    ])->fresh();

    $expectedThumbnailUrl = 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg';

    expect($media->thumbnail)->toBe($expectedThumbnailUrl);
});

it('returns thumbnail placeholder for non-photo media types', function (): void {
    $expectedPlaceholder = ImageService::placeholder();

    $mediaTypes = [
        MediaType::AUDIO,
        MediaType::DOCUMENT,
    ];

    foreach ($mediaTypes as $type) {
        $media = Media::factory()->create([
            'type' => $type,
            'source' => 'files/test-file.wav',
        ])->fresh();

        expect($media->thumbnail)->toBe($expectedPlaceholder);
    }
});

it('returns downloadUrl for non-video media', function (): void {
    Storage::shouldReceive('temporaryUrl')
        ->once()
        ->with('photos/test-image.jpg', Mockery::on(fn ($date) => $date->greaterThan(now())), [
            'ResponseContentDisposition' => 'attachment; filename="test.jpg"',
            'ResponseContentType' => 'image/jpeg',
        ])
        ->andReturn('https://fake-url/download');

    $media = Media::factory()->make([
        'type' => MediaType::PHOTO,
        'source' => 'photos/test-image.jpg',
        'filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    expect($media->downloadUrl)->toBe('https://fake-url/download');
});

it('returns null downloadUrl for video media', function (): void {
    $media = Media::factory()->make([
        'type' => MediaType::VIDEO,
        'source' => 'videos/test-video.mp4',
        'filename' => 'test.mp4',
        'mime_type' => 'video/mp4',
    ]);

    expect($media->downloadUrl)->toBeNull();
});
