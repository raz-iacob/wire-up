<?php

declare(strict_types=1);

use App\Actions\UpdatePageAction;
use App\Enums\PageStatus;
use App\Models\Page;

it('may update a page', function (): void {
    $page = Page::factory()->create([
        'title' => 'Old Title',
        'status' => PageStatus::DRAFT,
    ]);

    $action = app(UpdatePageAction::class);

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

    $action = app(UpdatePageAction::class);

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

    $action = app(UpdatePageAction::class);

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

    $action = app(UpdatePageAction::class);

    $action->handle($page, [
        'status' => PageStatus::SCHEDULED,
        'published_at' => $futureDate,
    ]);

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->published_at->timestamp)->toBe($futureDate->timestamp);
});

it('handles private status', function (): void {
    $page = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now(),
    ]);

    $action = app(UpdatePageAction::class);

    $action->handle($page, [
        'status' => PageStatus::PRIVATE,
    ]);

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PRIVATE)
        ->and($page->published_at)->toBeNull();
});
