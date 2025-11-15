<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;

test('to array', function (): void {
    $page = Page::factory()->create()->refresh();

    expect(array_keys($page->toArray()))
        ->toBe([
            'id',
            'name',
            'metadata',
            'status',
            'published_at',
            'created_at',
            'updated_at',
        ]);
});

it('has proper default status', function (): void {
    $page = Page::factory()->create();

    expect($page->status)->toBe(PageStatus::DRAFT)
        ->and($page->published_at)->toBeNull();
});

it('can have metadata', function (): void {
    $metadata = ['theme' => 'dark', 'layout' => 'full-width'];
    $page = Page::factory()->create([
        'metadata' => $metadata,
    ]);

    expect($page->metadata)->toBe($metadata)
        ->and($page->metadata['theme'])->toBe('dark')
        ->and($page->metadata['layout'])->toBe('full-width');
});

test('status enum casting works', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED]);

    expect($page->status)->toBeInstanceOf(PageStatus::class)
        ->and($page->status->value)->toBe('published');
});

it('can find pages by published scope', function (): void {
    $draft = Page::factory()->create([
        'status' => PageStatus::DRAFT,
        'published_at' => null,
    ]);
    $published = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $scheduled = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->addDay(),
    ]);
    $private = Page::factory()->create([
        'status' => PageStatus::PRIVATE,
        'published_at' => now()->subDay(),
    ]);

    $publishedPages = Page::published()->get();

    expect($publishedPages)->toHaveCount(1)
        ->and($publishedPages->contains($published))->toBeTrue()
        ->and($publishedPages->contains($scheduled))->toBeFalse()
        ->and($publishedPages->contains($draft))->toBeFalse()
        ->and($publishedPages->contains($private))->toBeFalse();
});
