<?php

declare(strict_types=1);

use App\Actions\DuplicatePageAction;
use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Page;

it('duplicates a page with its blocks and draft status', function (): void {
    $page = Page::factory()->create([
        'title' => ['en' => 'About'],
        'description' => ['en' => 'About us.'],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en'], 'layout' => ['hideHeader' => true]],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'about-src', 'base_path' => '']);
    $page->blocks()->create(['type' => 'rich-text', 'position' => 0, 'content' => ['body' => ['en' => 'Hi']]]);

    $media = Media::factory()->create(['type' => MediaType::IMAGE]);
    $page->media()->attach($media->id, ['role' => 'og_image', 'locale' => 'en', 'position' => 0, 'crop' => null, 'metadata' => null]);

    $copy = resolve(DuplicatePageAction::class)->handle($page);

    expect($copy->id)->not->toBe($page->id)
        ->and($copy->title)->toBe('Copy of About')
        ->and($copy->description)->toBe('About us.')
        ->and($copy->status)->toBe(ContentStatus::DRAFT)
        ->and($copy->published_at)->toBeNull()
        ->and($copy->metadata['layout']['hideHeader'] ?? null)->toBeTrue()
        ->and($copy->blocks()->count())->toBe(1)
        ->and($copy->media()->count())->toBe(1);

    $this->assertDatabaseHas('slugs', [
        'slug' => 'copy-of-about',
        'base_path' => '',
        'sluggable_type' => 'page',
        'sluggable_id' => $copy->id,
    ]);

    expect($page->fresh()->title)->toBe('About');
});
