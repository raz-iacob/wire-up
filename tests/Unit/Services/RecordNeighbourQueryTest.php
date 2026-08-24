<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\RecordNeighbourQuery;

function neighbourType(): RecordType
{
    return RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function neighbourRecord(RecordType $type, array $attributes = []): Record
{
    return Record::factory()->create([
        'record_type_id' => $type->id,
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

it('finds the records published either side of the current one', function (): void {
    $type = neighbourType();
    $older = neighbourRecord($type, ['published_at' => now()->subDays(5)]);
    $current = neighbourRecord($type, ['published_at' => now()->subDays(3)]);
    $newer = neighbourRecord($type, ['published_at' => now()->subDays(1)]);

    $result = (new RecordNeighbourQuery)->forRecord($current);

    expect($result['previous']?->id)->toBe($older->id)
        ->and($result['next']?->id)->toBe($newer->id);
});

it('returns null on each end of the series', function (): void {
    $type = neighbourType();
    $first = neighbourRecord($type, ['published_at' => now()->subDays(5)]);
    $last = neighbourRecord($type, ['published_at' => now()->subDays(1)]);

    expect((new RecordNeighbourQuery)->forRecord($first)['previous'])->toBeNull()
        ->and((new RecordNeighbourQuery)->forRecord($last)['next'])->toBeNull();
});

it('separates records that share a published_at by id', function (): void {
    $type = neighbourType();
    $stamp = now()->subDay();
    $first = neighbourRecord($type, ['published_at' => $stamp]);
    $second = neighbourRecord($type, ['published_at' => $stamp]);
    $third = neighbourRecord($type, ['published_at' => $stamp]);

    $result = (new RecordNeighbourQuery)->forRecord($second);

    expect($result['previous']?->id)->toBe($first->id)
        ->and($result['next']?->id)->toBe($third->id);
});

it('stays inside the record type', function (): void {
    $type = neighbourType();
    $other = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    $current = neighbourRecord($type, ['published_at' => now()->subDays(3)]);
    neighbourRecord($other, ['published_at' => now()->subDays(1)]);

    expect((new RecordNeighbourQuery)->forRecord($current)['next'])->toBeNull();
});

it('restricts to a shared category when asked', function (): void {
    $type = neighbourType();
    $category = Category::factory()->create();

    $current = neighbourRecord($type, ['published_at' => now()->subDays(3)]);
    $current->categories()->attach($category);

    $uncategorised = neighbourRecord($type, ['published_at' => now()->subDays(2)]);
    $sameCategory = neighbourRecord($type, ['published_at' => now()->subDay()]);
    $sameCategory->categories()->attach($category);

    expect((new RecordNeighbourQuery)->forRecord($current, true)['next']?->id)->toBe($sameCategory->id)
        ->and((new RecordNeighbourQuery)->forRecord($current)['next']?->id)->toBe($uncategorised->id);
});

it('skips drafts', function (): void {
    $type = neighbourType();
    $current = neighbourRecord($type, ['published_at' => now()->subDays(3)]);
    neighbourRecord($type, ['published_at' => now()->subDay(), 'status' => ContentStatus::DRAFT]);

    expect((new RecordNeighbourQuery)->forRecord($current)['next'])->toBeNull();
});

it('finds nothing for a record that was never published', function (): void {
    $type = neighbourType();
    neighbourRecord($type, ['published_at' => now()->subDays(3)]);
    $unpublished = neighbourRecord($type, ['published_at' => null, 'status' => ContentStatus::DRAFT]);

    expect((new RecordNeighbourQuery)->forRecord($unpublished))
        ->toBe(['previous' => null, 'next' => null]);
});
