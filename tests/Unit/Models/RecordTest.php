<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Models\RecordType;

it('casts json and enum columns', function (): void {
    $record = Record::factory()->create([
        'data' => ['price' => 42],
        'status' => ContentStatus::PUBLISHED,
    ]);

    expect($record->data)->toBe(['price' => 42])
        ->and($record->status)->toBe(ContentStatus::PUBLISHED)
        ->and($record->metadata)->toBeArray();
});

it('belongs to a record type', function (): void {
    $type = RecordType::factory()->create();
    $record = Record::factory()->create(['record_type_id' => $type->id]);

    expect($record->recordType->id)->toBe($type->id);
});

it('resolves title and description via translations', function (): void {
    $record = Record::factory()->create([
        'title' => ['en' => 'Hello'],
        'description' => ['en' => 'World'],
    ]);

    expect($record->title)->toBe('Hello')
        ->and($record->description)->toBe('World');
});

it('namespaces its slug under the record type prefix', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'projects']);
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Big Build']]);

    $record->setSlugs();

    $this->assertDatabaseHas('slugs', [
        'slug' => 'big-build',
        'base_path' => 'projects',
        'sluggable_type' => 'record',
        'sluggable_id' => $record->id,
    ]);
});

it('reports computed status and the published scope', function (): void {
    $type = RecordType::factory()->create();

    $published = Record::factory()->create(['record_type_id' => $type->id, 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $future = Record::factory()->create(['record_type_id' => $type->id, 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->addDay()]);
    Record::factory()->create(['record_type_id' => $type->id, 'status' => ContentStatus::DRAFT]);

    expect($future->computed_status)->toBe(ContentStatus::SCHEDULED)
        ->and($published->computed_status)->toBe(ContentStatus::PUBLISHED)
        ->and(Record::query()->published()->pluck('id')->all())->toBe([$published->id]);
});

it('reads noindex from metadata', function (): void {
    expect(Record::factory()->make(['metadata' => ['noindex' => true]])->isNoindex())->toBeTrue()
        ->and(Record::factory()->make(['metadata' => []])->isNoindex())->toBeFalse();
});
