<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Media;
use App\Models\Page;
use App\Models\Settings;
use App\Services\MediaUsageService;

it('reports no usage for a fresh media', function (): void {
    $media = Media::factory()->create();

    $service = resolve(MediaUsageService::class);

    expect($service->isInUse($media))->toBeFalse()
        ->and($service->labels($media))->toBe([]);
});

it('detects pivot usage when attached to a page', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create();

    $page->media()->attach($media, ['role' => 'og_image', 'locale' => app()->getLocale()]);

    $service = resolve(MediaUsageService::class);

    expect($service->isInUse($media))->toBeTrue()
        ->and($service->labels($media))->not->toBeEmpty();
});

it('labels pivot usage with the owner title when present', function (): void {
    $page = Page::factory()->create(['title' => 'About Us']);
    $media = Media::factory()->create();

    $page->media()->attach($media, ['role' => 'og_image', 'locale' => app()->getLocale()]);

    expect(resolve(MediaUsageService::class)->labels($media))
        ->toContain('Page: About Us');
});

it('detects usage embedded inside a block by source', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['source' => 'media/used-in-block.jpg']);

    Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => BlockType::TEXT_IMAGE,
        'content' => ['image' => ['id' => $media->id, 'source' => $media->source]],
    ]);

    $service = resolve(MediaUsageService::class);

    expect($service->isInUse($media))->toBeTrue()
        ->and($service->labels($media))->not->toBeEmpty();
});

it('detects usage inside a gallery block stored as an array of media', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['source' => 'media/gallery-item.jpg']);

    Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => BlockType::GALLERY,
        'content' => ['media' => [['id' => $media->id, 'source' => $media->source]]],
    ]);

    expect(resolve(MediaUsageService::class)->isInUse($media))->toBeTrue();
});

it('detects usage in site settings and labels it', function (): void {
    $media = Media::factory()->create(['source' => 'media/site-logo.jpg']);

    Settings::set(['logo' => ['source' => $media->source]]);

    $service = resolve(MediaUsageService::class);

    expect($service->isInUse($media))->toBeTrue()
        ->and($service->labels($media))->toContain(__('Site settings'));
});

it('does not match a different media source', function (): void {
    $media = Media::factory()->create(['source' => 'media/unique-needle.jpg']);
    $page = Page::factory()->create();

    Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => BlockType::TEXT_IMAGE,
        'content' => ['image' => ['source' => 'media/some-other-file.jpg']],
    ]);

    expect(resolve(MediaUsageService::class)->isInUse($media))->toBeFalse();
});
