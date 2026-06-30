<?php

declare(strict_types=1);

use App\Actions\UpdatePageAction;
use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Page;

it('may update a page', function (): void {
    $page = Page::factory()->create([
        'title' => 'Old Title',
        'status' => ContentStatus::DRAFT,
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'title' => 'New Title',
        'status' => ContentStatus::PUBLISHED,
        'slugs' => ['en' => 'new-slug'],
    ]);

    $page->refresh();

    expect($page->title)->toBe('New Title')
        ->and($page->slug)->toBe('new-slug')
        ->and($page->status)->toBe(ContentStatus::PUBLISHED)
        ->and($page->published_at)->not->toBeNull();
});

it('handles draft status', function (): void {
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now(),
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'status' => ContentStatus::DRAFT,
    ]);

    $page->refresh();

    expect($page->status)->toBe(ContentStatus::DRAFT)
        ->and($page->published_at)->toBeNull();
});

it('handles published status', function (): void {
    $page = Page::factory()->create([
        'status' => ContentStatus::DRAFT,
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'status' => ContentStatus::PUBLISHED,
    ]);

    $page->refresh();

    expect($page->status)->toBe(ContentStatus::PUBLISHED)
        ->and($page->published_at)->not->toBeNull();
});

it('handles scheduled status', function (): void {
    $futureDate = now()->addDay();

    $page = Page::factory()->create([
        'status' => ContentStatus::DRAFT,
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'status' => ContentStatus::SCHEDULED,
        'published_at' => $futureDate,
    ]);

    $page->refresh();

    expect($page->status)->toBe(ContentStatus::PUBLISHED)
        ->and($page->published_at->timestamp)->toBe($futureDate->timestamp);
});

it('persists og_image media per locale with crop and order', function (): void {
    $page = Page::factory()->create(['status' => ContentStatus::DRAFT]);
    $first = Media::factory()->create(['type' => MediaType::IMAGE]);
    $second = Media::factory()->create(['type' => MediaType::IMAGE]);

    resolve(UpdatePageAction::class)->handle($page, [
        'status' => ContentStatus::DRAFT,
        'og_image' => [
            'en' => [
                ['id' => $first->id, 'crop' => ['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]]],
                ['id' => $second->id, 'crop' => []],
            ],
        ],
    ]);

    $items = $page->media()->wherePivot('role', 'og_image')->wherePivot('locale', 'en')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->id)->toBe($first->id)
        ->and($items[0]->pivot->position)->toBe(0)
        ->and($items[1]->id)->toBe($second->id);
});

it('does not touch media when og_image is absent', function (): void {
    $page = Page::factory()->create(['status' => ContentStatus::DRAFT]);
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);
    $page->media()->attach($media, ['role' => 'og_image', 'locale' => 'en']);

    resolve(UpdatePageAction::class)->handle($page, [
        'status' => ContentStatus::PUBLISHED,
    ]);

    expect($page->media()->wherePivot('role', 'og_image')->count())->toBe(1);
});

it('handles private status', function (): void {
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now(),
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'status' => ContentStatus::PRIVATE,
    ]);

    $page->refresh();

    expect($page->status)->toBe(ContentStatus::PRIVATE)
        ->and($page->published_at)->toBeNull();
});
