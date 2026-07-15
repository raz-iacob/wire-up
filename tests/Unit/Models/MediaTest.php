<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\MediaType;
use App\Models\Block;
use App\Models\Media;
use App\Models\Mediable;
use App\Models\Page;
use App\Models\Settings;
use App\Services\ImageService;
use Illuminate\Support\Facades\File;
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
            'metadata',
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
    $page = Page::factory()->create();
    $media = Media::factory()->create();

    $page->media()->attach($media, ['role' => 'example', 'locale' => app()->getLocale()]);
    $anotherPage = Page::factory()->create();
    $anotherPage->media()->attach($media, ['role' => 'example2', 'locale' => app()->getLocale()]);

    expect($media->mediables)->toHaveCount(2)
        ->and($media->mediables->first())->toBeInstanceOf(Mediable::class);
});

it('returns temporary url for image type with expires parameter', function (): void {
    Storage::fake();
    $media = Media::factory()->create([
        'type' => MediaType::IMAGE,
        'source' => 'photos/test-image.jpg',
    ])->fresh();

    $url = $media->url;

    expect($url)->toBeString()
        ->and($url)->toContain('photos/test-image.jpg')
        ->and($url)->toContain('expiration=');
});

it('returns image preview URL for image media type', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::IMAGE,
        'source' => 'photos/test-image.jpg',
    ])->fresh();

    $expectedPreviewUrl = ImageService::url('w=350,h=200', 'photos/test-image.jpg');

    expect($media->preview)->toBe($expectedPreviewUrl);
});

it('returns a medium uncropped source URL for image crop editing', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::IMAGE,
        'source' => 'photos/test-image.jpg',
    ])->fresh();

    expect($media->cropSrc)->toBe(ImageService::url('w=1200', 'photos/test-image.jpg'));
});

it('returns a placeholder crop source for non-image media types', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::DOCUMENT,
        'source' => 'files/test-file.pdf',
    ])->fresh();

    expect($media->cropSrc)->toBe(ImageService::placeholder());
});

it('returns video preview URL for video media type when thumbnail is not null', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::VIDEO,
        'filename' => 'dQw4w9WgXcQ',
        'thumbnail' => 'videos/test-video.mp4',
    ])->fresh();

    $expectedPreviewUrl = ImageService::url('w=350,h=200', 'videos/test-video.mp4');

    expect($media->preview)->toBe($expectedPreviewUrl);
});

it('returns preview placeholder for non-image media types when thumbnail is null', function (): void {
    $expectedPlaceholder = ImageService::placeholder();

    $mediaTypes = [
        MediaType::AUDIO,
        MediaType::DOCUMENT,
    ];

    foreach ($mediaTypes as $type) {
        $media = Media::factory()->create([
            'type' => $type,
            'source' => 'files/test-file.wav',
            'thumbnail' => null,
        ])->fresh();

        expect($media->preview)->toBe($expectedPlaceholder);
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
        'type' => MediaType::IMAGE,
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

it('deletes media and files when safe to delete', function (): void {
    Storage::fake(config('filesystems.media'));

    $media = Media::factory()->create([
        'source' => 'media/test.jpg',
        'thumbnail' => 'media/test_thumb.jpg',
    ]);

    Storage::disk(config('filesystems.media'))->put('media/test.jpg', 'content');
    Storage::disk(config('filesystems.media'))->put('media/test_thumb.jpg', 'thumbnail');

    $mediaId = $media->id;

    expect($media->delete())->toBeTrue()
        ->and(Media::query()->where('id', $mediaId)->exists())->toBeFalse()
        ->and(Storage::disk(config('filesystems.media'))->exists('media/test.jpg'))->toBeFalse()
        ->and(Storage::disk(config('filesystems.media'))->exists('media/test_thumb.jpg'))->toBeFalse();
});

it('deletes media without thumbnail', function (): void {
    Storage::fake(config('filesystems.media'));

    $media = Media::factory()->create([
        'source' => 'media/document.pdf',
        'thumbnail' => null,
    ]);

    Storage::disk(config('filesystems.media'))->put('media/document.pdf', 'content');

    $mediaId = $media->id;

    expect($media->delete())->toBeTrue()
        ->and(Media::query()->where('id', $mediaId)->exists())->toBeFalse()
        ->and(Storage::disk(config('filesystems.media'))->exists('media/document.pdf'))->toBeFalse();
});

it('prevents deletion when media is attached to pages', function (): void {
    Storage::fake(config('filesystems.media'));

    $page = Page::factory()->create();
    $media = Media::factory()->create([
        'source' => 'media/test.jpg',
    ]);

    $page->media()->attach($media, ['role' => 'example', 'locale' => app()->getLocale()]);

    Storage::disk(config('filesystems.media'))->put('media/test.jpg', 'content');

    $mediaId = $media->id;

    expect($media->delete())->toBeFalse()
        ->and(Media::query()->where('id', $mediaId)->exists())->toBeTrue()
        ->and(Storage::disk(config('filesystems.media'))->exists('media/test.jpg'))->toBeTrue();
});

it('can delete safely returns true when no mediables exist', function (): void {
    $media = Media::factory()->create();

    expect($media->delete())->toBeTrue();
});

it('can delete safely returns false when mediables exist', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create();

    $page->media()->attach($media, ['role' => 'example', 'locale' => app()->getLocale()]);

    expect($media->delete())->toBeFalse();
});

it('prevents deletion when media is embedded in a block', function (): void {
    Storage::fake(config('filesystems.media'));

    $page = Page::factory()->create();
    $media = Media::factory()->create(['source' => 'media/in-block.jpg']);
    Storage::disk(config('filesystems.media'))->put('media/in-block.jpg', 'content');

    Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => BlockType::TEXT_IMAGE,
        'content' => ['image' => ['source' => 'media/in-block.jpg']],
    ]);

    expect($media->delete())->toBeFalse()
        ->and(Media::query()->whereKey($media->id)->exists())->toBeTrue()
        ->and(Storage::disk(config('filesystems.media'))->exists('media/in-block.jpg'))->toBeTrue();
});

it('prevents deletion when media is used in site settings', function (): void {
    Storage::fake(config('filesystems.media'));

    $media = Media::factory()->create(['source' => 'media/site-logo.jpg']);
    Storage::disk(config('filesystems.media'))->put('media/site-logo.jpg', 'content');

    Settings::set(['logo' => ['source' => 'media/site-logo.jpg']]);

    expect($media->delete())->toBeFalse()
        ->and(Media::query()->whereKey($media->id)->exists())->toBeTrue();
});

it('purges cached image variants when the media is deleted', function (): void {
    Storage::fake(config('filesystems.media'));

    $media = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'photos/purge-me.jpg']);

    $dir = config('media.cache_path').'/'.hash('sha256', 'photos/purge-me.jpg');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/variant.jpg', 'cached');

    expect($media->delete())->toBeTrue()
        ->and(File::isDirectory($dir))->toBeFalse();
});
