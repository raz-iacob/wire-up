<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Actions\UpdateRecordAction;
use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\RecordType;

it('updates data, slug, blocks and media', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'products']);
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'Item']);
    $media = Media::factory()->create();

    resolve(UpdateRecordAction::class)->handle($record, [
        'title' => 'Renamed',
        'data' => ['price' => 42],
        'status' => ContentStatus::PUBLISHED,
        'slugs' => ['en' => 'renamed'],
        'blocks' => ['new-1' => ['id' => 'new-1', 'type' => 'rich-text', 'position' => 0, 'content' => []]],
        'media' => ['og_image' => ['en' => [['id' => $media->id]]]],
    ]);

    $record->refresh()->load('translations', 'slugs', 'blocks', 'media');

    expect($record->title)->toBe('Renamed')
        ->and($record->data)->toBe(['price' => 42])
        ->and($record->status)->toBe(ContentStatus::PUBLISHED)
        ->and($record->published_at)->not->toBeNull()
        ->and($record->getSlug('en'))->toBe('renamed')
        ->and($record->blocks)->toHaveCount(1)
        ->and($record->media)->toHaveCount(1);
});

it('syncs categories on update', function (): void {
    $type = RecordType::factory()->create();
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'Item']);
    $first = Category::factory()->create();
    $second = Category::factory()->create();

    resolve(UpdateRecordAction::class)->handle($record, [
        'categories' => [$first->id, $second->id],
    ]);

    expect($record->refresh()->categories()->pluck('categories.id')->all())
        ->toEqualCanonicalizing([$first->id, $second->id]);

    resolve(UpdateRecordAction::class)->handle($record, [
        'categories' => [$second->id],
    ]);

    expect($record->refresh()->categories()->pluck('categories.id')->all())->toBe([$second->id]);
});

it('applies the publication status on update', function (ContentStatus $input, string $expectedStatus, bool $hasDate): void {
    $type = RecordType::factory()->create();
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'X']);

    resolve(UpdateRecordAction::class)->handle($record, [
        'status' => $input,
        'published_at' => now()->addWeek(),
    ]);

    $record->refresh();

    expect($record->status->value)->toBe($expectedStatus)
        ->and($record->published_at !== null)->toBe($hasDate);
})->with([
    'draft' => [ContentStatus::DRAFT, 'draft', false],
    'published' => [ContentStatus::PUBLISHED, 'published', true],
    'scheduled' => [ContentStatus::SCHEDULED, 'published', true],
    'private' => [ContentStatus::PRIVATE, 'private', false],
]);
