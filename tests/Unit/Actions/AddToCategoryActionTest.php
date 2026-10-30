<?php

declare(strict_types=1);

use App\Actions\AddToCategoryAction;
use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Record;

it('adds a published page to a category and keeps its status, date and other categories', function (): void {
    $publishedAt = now()->subWeek()->startOfSecond();
    $page = Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => $publishedAt]);
    $existing = Category::factory()->create();
    $page->categories()->attach($existing);
    $news = Category::factory()->create();

    $added = resolve(AddToCategoryAction::class)->handle($page, $news);

    $fresh = $page->fresh();

    expect($added)->toBeTrue()
        ->and($fresh->categories->modelKeys())->toEqualCanonicalizing([$existing->id, $news->id])
        ->and($fresh->status)->toBe(ContentStatus::PUBLISHED)
        ->and($fresh->published_at?->equalTo($publishedAt))->toBeTrue();
});

it('keeps a scheduled record scheduled for its original date', function (): void {
    $goesLiveAt = now()->addWeek()->startOfSecond();
    $record = Record::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => $goesLiveAt]);
    $news = Category::factory()->create();

    resolve(AddToCategoryAction::class)->handle($record, $news);

    $fresh = $record->fresh();

    expect($fresh->categories->modelKeys())->toBe([$news->id])
        ->and($fresh->computed_status)->toBe(ContentStatus::SCHEDULED)
        ->and($fresh->published_at?->equalTo($goesLiveAt))->toBeTrue();
});

it('leaves content that is already in the category untouched', function (): void {
    $page = Page::factory()->create();
    $news = Category::factory()->create();
    $page->categories()->attach($news);
    $revisions = $page->revisions()->count();

    $added = resolve(AddToCategoryAction::class)->handle($page, $news);

    expect($added)->toBeFalse()
        ->and($page->revisions()->count())->toBe($revisions);
});
