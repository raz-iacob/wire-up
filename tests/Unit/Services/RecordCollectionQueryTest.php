<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\RecordCollectionQuery;

function collectionType(): RecordType
{
    return RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function publishedCollectionRecord(RecordType $type, array $attributes = []): Record
{
    return Record::factory()->create([
        'record_type_id' => $type->id,
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

it('returns nothing when no record type is set', function (): void {
    expect((new RecordCollectionQuery)->resolve([]))->toBeEmpty();
});

it('returns latest published records newest-first, capped by the limit', function (): void {
    $type = collectionType();
    $oldest = publishedCollectionRecord($type, ['published_at' => now()->subDays(5)]);
    $middle = publishedCollectionRecord($type, ['published_at' => now()->subDays(3)]);
    $newest = publishedCollectionRecord($type, ['published_at' => now()->subDays(1)]);

    $result = (new RecordCollectionQuery)->resolve([
        'recordTypeId' => $type->id,
        'source' => 'latest',
        'limit' => 2,
    ]);

    expect($result->pluck('id')->all())->toBe([$newest->id, $middle->id]);
});

it('defaults to the latest source when none or an unknown source is given', function (): void {
    $type = collectionType();
    $record = publishedCollectionRecord($type);

    $result = (new RecordCollectionQuery)->resolve([
        'recordTypeId' => $type->id,
        'source' => 'nonsense',
        'limit' => 10,
    ]);

    expect($result->pluck('id')->all())->toBe([$record->id]);
});

it('excludes drafts and future-dated records', function (): void {
    $type = collectionType();
    $live = publishedCollectionRecord($type);
    Record::factory()->create(['record_type_id' => $type->id, 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->addDay()]);
    Record::factory()->create(['record_type_id' => $type->id, 'status' => ContentStatus::DRAFT, 'published_at' => null]);

    $result = (new RecordCollectionQuery)->resolve(['recordTypeId' => $type->id, 'source' => 'latest', 'limit' => 10]);

    expect($result->pluck('id')->all())->toBe([$live->id]);
});

it('resolves hand-picked records in the chosen order', function (): void {
    $type = collectionType();
    $a = publishedCollectionRecord($type);
    publishedCollectionRecord($type);
    $c = publishedCollectionRecord($type);

    $result = (new RecordCollectionQuery)->resolve([
        'recordTypeId' => $type->id,
        'source' => 'manual',
        'recordIds' => [(string) $c->id, (string) $a->id],
    ]);

    expect($result->pluck('id')->all())->toBe([$c->id, $a->id]);
});

it('returns nothing for a manual source with no picked records', function (): void {
    $type = collectionType();

    expect((new RecordCollectionQuery)->resolve(['recordTypeId' => $type->id, 'source' => 'manual', 'recordIds' => []]))->toBeEmpty();
});

it('filters records by category', function (): void {
    $type = collectionType();
    $category = Category::factory()->create();
    $tagged = publishedCollectionRecord($type);
    $tagged->categories()->attach($category->id);
    publishedCollectionRecord($type);

    $result = (new RecordCollectionQuery)->resolve([
        'recordTypeId' => $type->id,
        'source' => 'category',
        'categoryId' => $category->id,
        'limit' => 10,
    ]);

    expect($result->pluck('id')->all())->toBe([$tagged->id]);
});

it('returns nothing for a category source with no category chosen', function (): void {
    $type = collectionType();
    publishedCollectionRecord($type);

    expect((new RecordCollectionQuery)->resolve(['recordTypeId' => $type->id, 'source' => 'category', 'categoryId' => null]))->toBeEmpty();
});

it('paginates latest records newest-first by page', function (): void {
    $type = collectionType();
    $records = collect(range(1, 5))->map(fn (int $i): Record => publishedCollectionRecord($type, ['published_at' => now()->subDays(10 - $i)]));
    $page1 = (new RecordCollectionQuery)->paginate(['recordTypeId' => $type->id, 'source' => 'latest'], 2, 1);
    $page2 = (new RecordCollectionQuery)->paginate(['recordTypeId' => $type->id, 'source' => 'latest'], 2, 2);

    expect($page1->total())->toBe(5)
        ->and($page1->pluck('id')->all())->toBe([$records[4]->id, $records[3]->id])
        ->and($page2->pluck('id')->all())->toBe([$records[2]->id, $records[1]->id])
        ->and($page1->hasMorePages())->toBeTrue();
});

it('paginates hand-picked records in order across pages', function (): void {
    $type = collectionType();
    $a = publishedCollectionRecord($type);
    $b = publishedCollectionRecord($type);
    $c = publishedCollectionRecord($type);

    $content = ['recordTypeId' => $type->id, 'source' => 'manual', 'recordIds' => [(string) $c->id, (string) $a->id, (string) $b->id]];

    $page1 = (new RecordCollectionQuery)->paginate($content, 2, 1);
    $page2 = (new RecordCollectionQuery)->paginate($content, 2, 2);

    expect($page1->total())->toBe(3)
        ->and($page1->pluck('id')->all())->toBe([$c->id, $a->id])
        ->and($page2->pluck('id')->all())->toBe([$b->id])
        ->and($page2->hasMorePages())->toBeFalse();
});

it('returns an empty paginator when no record type is set', function (): void {
    $paginator = (new RecordCollectionQuery)->paginate([], 6, 1);

    expect($paginator->total())->toBe(0)
        ->and($paginator->isEmpty())->toBeTrue();
});

it('returns an empty paginator for a category source with no category', function (): void {
    $type = collectionType();
    publishedCollectionRecord($type);

    $paginator = (new RecordCollectionQuery)->paginate(['recordTypeId' => $type->id, 'source' => 'category', 'categoryId' => null], 6, 1);

    expect($paginator->total())->toBe(0);
});
