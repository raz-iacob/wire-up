<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Locale;
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

it('reports live status per locale when multilingual', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);
    cache()->forget('site-locales');

    $record = Record::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);

    expect($record->isLiveInLocale('en'))->toBeTrue()
        ->and($record->isLiveInLocale('fr'))->toBeFalse();
});

it('is not live when it is a draft', function (): void {
    $record = Record::factory()->create(['status' => ContentStatus::DRAFT, 'published_at' => null]);

    expect($record->isLiveInLocale())->toBeFalse();
});

it('reads noindex from metadata', function (): void {
    expect(Record::factory()->make(['metadata' => ['noindex' => true]])->isNoindex())->toBeTrue()
        ->and(Record::factory()->make(['metadata' => []])->isNoindex())->toBeFalse();
});

it('builds a namespaced url from its slug prefix', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'projects']);
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Big Build']]);
    $record->setSlugs();

    expect($record->getUrl())->toBe(url('projects/big-build'));
});

it('resolves shared and translatable field values from data', function (): void {
    $record = Record::factory()->make([
        'data' => [
            'price' => 42,
            'headline' => ['en' => 'English', 'fr' => 'French'],
        ],
    ]);

    expect($record->fieldValue('price', false))->toBe(42)
        ->and($record->fieldValue('headline', true))->toBe('English')
        ->and($record->fieldValue('missing', false))->toBeNull()
        ->and($record->fieldValue('price', true))->toBeNull();
});
