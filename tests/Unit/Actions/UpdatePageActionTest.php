<?php

declare(strict_types=1);

use App\Actions\UpdatePageAction;
use App\Enums\MediaType;
use App\Enums\PageStatus;
use App\Models\Media;
use App\Models\Page;

it('may update a page', function (): void {
    $page = Page::factory()->create([
        'title' => 'Old Title',
        'status' => PageStatus::DRAFT,
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'title' => 'New Title',
        'status' => PageStatus::PUBLISHED,
        'slugs' => ['en' => 'new-slug'],
    ]);

    $page->refresh();

    expect($page->title)->toBe('New Title')
        ->and($page->slug)->toBe('new-slug')
        ->and($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->published_at)->not->toBeNull();
});

it('handles draft status', function (): void {
    $page = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now(),
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'status' => PageStatus::DRAFT,
    ]);

    $page->refresh();

    expect($page->status)->toBe(PageStatus::DRAFT)
        ->and($page->published_at)->toBeNull();
});

it('handles published status', function (): void {
    $page = Page::factory()->create([
        'status' => PageStatus::DRAFT,
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'status' => PageStatus::PUBLISHED,
    ]);

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->published_at)->not->toBeNull();
});

it('handles scheduled status', function (): void {
    $futureDate = now()->addDay();

    $page = Page::factory()->create([
        'status' => PageStatus::DRAFT,
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'status' => PageStatus::SCHEDULED,
        'published_at' => $futureDate,
    ]);

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->published_at->timestamp)->toBe($futureDate->timestamp);
});

it('persists og_image media per locale with crop and order', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::DRAFT]);
    $first = Media::factory()->create(['type' => MediaType::IMAGE]);
    $second = Media::factory()->create(['type' => MediaType::IMAGE]);

    resolve(UpdatePageAction::class)->handle($page, [
        'status' => PageStatus::DRAFT,
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
    $page = Page::factory()->create(['status' => PageStatus::DRAFT]);
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);
    $page->media()->attach($media, ['role' => 'og_image', 'locale' => 'en']);

    resolve(UpdatePageAction::class)->handle($page, [
        'status' => PageStatus::PUBLISHED,
    ]);

    expect($page->media()->wherePivot('role', 'og_image')->count())->toBe(1);
});

it('handles private status', function (): void {
    $page = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now(),
    ]);

    $action = resolve(UpdatePageAction::class);

    $action->handle($page, [
        'status' => PageStatus::PRIVATE,
    ]);

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PRIVATE)
        ->and($page->published_at)->toBeNull();
});
