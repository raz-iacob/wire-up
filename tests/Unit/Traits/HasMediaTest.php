<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Page;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

it('defines a MorphToMany relationship with the Media model', function (): void {
    $page = Page::factory()->create();

    expect($page->media())->toBeInstanceOf(MorphToMany::class);
});

it('can find first media by type and role in current locale', function (): void {
    $page = Page::factory()->create();

    $video1Media = Media::factory()->create(['type' => MediaType::VIDEO]);
    $video2Media = Media::factory()->create(['type' => MediaType::VIDEO]);

    $page->media()->attach($video1Media, ['role' => 'trailer', 'locale' => app()->getLocale()]);
    $page->media()->attach($video2Media, ['role' => 'trailer', 'locale' => app()->getLocale()]);

    $video = $page->firstMedia(MediaType::VIDEO, 'trailer');

    expect($video)->toBeInstanceOf(Media::class)
        ->and($video->id)->toBe($video1Media->id);
});

it('returns null if no media found for given type and role', function (): void {
    $page = Page::factory()->create();

    $media = $page->firstMedia(MediaType::AUDIO, 'background');

    expect($media)->toBeNull();
});

it('can retrieve all media by type and role in current locale', function (): void {
    $page = Page::factory()->create();

    $document1Media = Media::factory()->create(['type' => MediaType::DOCUMENT]);
    $document2Media = Media::factory()->create(['type' => MediaType::DOCUMENT]);

    $page->media()->attach($document1Media, ['role' => 'contract', 'locale' => app()->getLocale()]);
    $page->media()->attach($document2Media, ['role' => 'contract', 'locale' => app()->getLocale()]);
    $documents = $page->allMedia(MediaType::DOCUMENT, 'contract');

    expect($documents)->toBeArray()
        ->and(count($documents))->toBe(2)
        ->and($documents[0]->id)->toBe($document1Media->id)
        ->and($documents[1]->id)->toBe($document2Media->id);
});

it('can check if a model has an image', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => MediaType::PHOTO]);

    $page->media()->attach($media, ['role' => 'cover', 'crop' => ['default' => [0, 0, 100, 100]], 'locale' => app()->getLocale()]);

    expect($page->hasImage('cover'))->toBeTrue();
});

it('can return a specific image by role', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create([
        'type' => MediaType::PHOTO,
        'source' => 'images/sample-photo.jpg',
    ]);

    $page->media()->attach($media, ['role' => 'cover', 'locale' => 'en', 'crop' => ['default' => [0, 0, 100, 100]]]);

    $image = $page->image('cover');

    expect($image)->toBeUrl()
        ->and($image)->toContain('images/sample-photo.jpg');
});

it('will return a placeholder image if no image is found', function (): void {
    $page = Page::factory()->create();
    $image = $page->image('cover');

    expect($image)->toBe(ImageService::placeholder());
});

it('will return null if no image is found and fallback is false', function (): void {
    $page = Page::factory()->create();
    $image = $page->image('cover', fallback: false);

    expect($image)->toBeNull();
});

it('can return all images for a role', function (): void {
    $page = Page::factory()->create();
    $media1 = Media::factory()->create(['type' => MediaType::PHOTO]);
    $media2 = Media::factory()->create(['type' => MediaType::PHOTO]);
    $page->media()->attach($media1, ['role' => 'gallery', 'locale' => 'en', 'crop' => ['default' => [0, 0, 100, 100]]]);
    $page->media()->attach($media2, ['role' => 'gallery', 'locale' => 'en', 'crop' => ['default' => [0, 0, 200, 200]]]);

    $images = $page->images('gallery');

    expect($images)->toBeArray()
        ->and(count($images))->toBe(2);
});

it('can find an image by role and crop', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => MediaType::PHOTO]);
    $page->media()->attach($media, ['role' => 'avatar', 'locale' => 'en', 'crop' => ['thumb' => [10, 10, 50, 50]]]);

    $found = (fn () => $this->findImage('avatar', 'thumb'))->call($page);

    expect($found)->not->toBeNull()
        ->and($found->pivot->role)->toBe('avatar');
});

it('can get crop string for an image', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => MediaType::PHOTO]);
    $page->media()->attach($media, ['role' => 'cover', 'locale' => 'en', 'crop' => ['default' => ['w' => 100, 'h' => 100, 'x' => 0, 'y' => 0]]]);

    $image = $page->media()->first();
    $params = ['w' => 200, 'h' => 200];

    $cropString = (fn () => $this->cropString([...($image->pivot->crop['default'] ?? []), ...$params]))->call($page);

    expect($cropString)->toContain('w=200')
        ->and($cropString)->toContain('h=200');
});

it('detaches media on model deletion', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => MediaType::PHOTO]);
    $page->media()->attach($media, ['role' => 'cover', 'locale' => 'en', 'crop' => ['default' => [0, 0, 100, 100]]]);

    $page->delete();

    $this->assertDatabaseMissing('mediables', [
        'mediable_id' => $page->id,
        'media_id' => $media->id,
    ]);
});

it('gets media alt text', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create([
        'type' => MediaType::PHOTO,
        'alt_text' => 'Sample Alt Text',
    ]);

    $page->media()->attach($media, ['role' => 'cover', 'locale' => 'en', 'crop' => ['default' => [0, 0, 100, 100]]]);

    $altText = $page->imageAltText('cover');

    expect($altText)->toBe('Sample Alt Text');
});

it('gets media caption from pivot metadata', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => MediaType::PHOTO]);

    $page->media()->attach($media, [
        'role' => 'cover',
        'locale' => 'en',
        'crop' => ['default' => [0, 0, 100, 100]],
        'metadata' => ['caption' => 'Test Caption'],
    ]);

    $caption = $page->imageCaption('cover');

    expect($caption)->toBe('Test Caption');
});
