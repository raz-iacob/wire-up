<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('filesystems.media'));
    Storage::disk(config('filesystems.media'))->put('media/test.jpg', file_get_contents(__DIR__.'/../Fixtures/test-image.jpg'));
});

it('builds an image url from the storage key, not the temporary url', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/test.jpg']);

    $page->syncMediaForRole('og_image', 'en', [
        ['id' => $media->id, 'crop' => ['default' => ['crop_w' => 20, 'crop_h' => 20, 'crop_x' => 10, 'crop_y' => 10, 'w' => 50, 'h' => 50]]],
    ]);
    $page->load('media');

    $url = $page->image('og_image', 'default');

    expect($url)
        ->toBeString()
        ->toContain('/img/')
        ->toContain('media/test.jpg');
});

it('resolves the generated image url through the image controller', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/test.jpg']);

    $page->syncMediaForRole('og_image', 'en', [
        ['id' => $media->id, 'crop' => ['default' => ['crop_w' => 20, 'crop_h' => 20, 'crop_x' => 10, 'crop_y' => 10, 'w' => 50, 'h' => 50]]],
    ]);
    $page->load('media');

    $url = $page->image('og_image', 'default');

    $response = $this->get($url);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/jpeg');
});
