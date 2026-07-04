<?php

declare(strict_types=1);

use App\Actions\DuplicateRecordAction;
use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Category;
use App\Models\Media;
use App\Models\Record;
use App\Models\RecordType;

it('duplicates a record with its blocks, media, data and categories', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'products']);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Widget'],
        'description' => ['en' => 'A fine widget.'],
        'data' => ['price' => 10, 'sku' => 'ABC'],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en'], 'noindex' => true],
    ]);
    $record->slugs()->create(['locale' => 'en', 'slug' => 'widget', 'base_path' => 'products']);
    $record->blocks()->create(['type' => 'rich-text', 'position' => 0, 'content' => ['body' => ['en' => 'Hello']]]);

    $category = Category::factory()->create(['name' => ['en' => 'Tools']]);
    $record->categories()->attach($category->id);

    $media = Media::factory()->create(['type' => MediaType::IMAGE]);
    $record->media()->attach($media->id, ['role' => 'gallery', 'locale' => 'en', 'position' => 0, 'crop' => null, 'metadata' => null]);

    $copy = resolve(DuplicateRecordAction::class)->handle($record);

    expect($copy->id)->not->toBe($record->id)
        ->and($copy->record_type_id)->toBe($type->id)
        ->and($copy->title)->toBe('Copy of Widget')
        ->and($copy->description)->toBe('A fine widget.')
        ->and($copy->status)->toBe(ContentStatus::DRAFT)
        ->and($copy->published_at)->toBeNull()
        ->and($copy->data)->toBe(['price' => 10, 'sku' => 'ABC'])
        ->and($copy->metadata['noindex'] ?? null)->toBeTrue()
        ->and($copy->blocks()->count())->toBe(1)
        ->and($copy->categories()->count())->toBe(1)
        ->and($copy->media()->count())->toBe(1);

    $this->assertDatabaseHas('slugs', [
        'slug' => 'copy-of-widget',
        'base_path' => 'products',
        'sluggable_type' => 'record',
        'sluggable_id' => $copy->id,
    ]);

    $original = $record->fresh();
    expect($original->title)->toBe('Widget')
        ->and($original->status)->toBe(ContentStatus::PUBLISHED);
});
