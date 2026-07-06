<?php

declare(strict_types=1);

use App\Models\Record;
use App\Models\RecordType;

it('casts json and scalar columns', function (): void {
    $type = RecordType::factory()->create([
        'name' => 'Product',
        'fields' => [['key' => 'price', 'type' => 'number']],
    ]);

    expect($type->name)->toBe('Product')
        ->and($type->fields)->toBeArray()
        ->and($type->position)->toBeInt();
});

it('uses key as the route key', function (): void {
    expect((new RecordType)->getRouteKeyName())->toBe('key');
});

it('has many records and reports whether it is in use', function (): void {
    $type = RecordType::factory()->create();

    expect($type->isInUse())->toBeFalse();

    Record::factory()->create(['record_type_id' => $type->id]);

    expect($type->refresh()->records)->toHaveCount(1)
        ->and($type->isInUse())->toBeTrue();
});

it('resolves index, searchable, and sortable fields from the blueprint', function (): void {
    $type = RecordType::factory()->create([
        'fields' => [
            ['key' => 'sku', 'type' => 'text', 'column' => true, 'searchable' => true, 'sortable' => true],
            ['key' => 'price', 'type' => 'money', 'column' => true, 'sortable' => true],
            ['key' => 'gallery', 'type' => 'media-gallery', 'column' => true, 'searchable' => true, 'sortable' => true],
            ['key' => 'notes', 'type' => 'textarea'],
        ],
    ]);

    expect(array_column($type->indexColumnFields(), 'key'))->toBe(['sku', 'price', 'gallery'])
        ->and(array_column($type->searchableFields(), 'key'))->toBe(['sku'])
        ->and($type->sortableFieldKeys())->toBe(['sku', 'price'])
        ->and($type->hasMediaColumns())->toBeTrue();
});

it('reports no media columns when none are media types', function (): void {
    $type = RecordType::factory()->create([
        'fields' => [['key' => 'sku', 'type' => 'text', 'column' => true]],
    ]);

    expect($type->hasMediaColumns())->toBeFalse();
});

it('resolves a field label from locale, first translation, or key', function (): void {
    $type = RecordType::factory()->create();

    expect($type->fieldLabel(['key' => 'sku', 'label' => ['en' => 'SKU', 'fr' => 'RÉF']]))->toBe('SKU')
        ->and($type->fieldLabel(['key' => 'sku', 'label' => ['fr' => 'RÉF']]))->toBe('RÉF')
        ->and($type->fieldLabel(['key' => 'sku', 'label' => []]))->toBe('sku')
        ->and($type->fieldLabel(['key' => 'sku']))->toBe('sku');
});

it('finds a field by key', function (): void {
    $type = RecordType::factory()->create([
        'fields' => [['key' => 'sku', 'type' => 'text']],
    ]);

    expect($type->fieldByKey('sku'))->toMatchArray(['key' => 'sku'])
        ->and($type->fieldByKey('missing'))->toBeNull();
});

it('detects an image-bearing field in the blueprint', function (): void {
    $withPhoto = RecordType::factory()->create(['fields' => [['key' => 'photo', 'type' => 'photo']]]);
    $withGallery = RecordType::factory()->create(['fields' => [['key' => 'g', 'type' => 'media-gallery']]]);
    $textOnly = RecordType::factory()->create(['fields' => [['key' => 'name', 'type' => 'text']]]);
    $videoOnly = RecordType::factory()->create(['fields' => [['key' => 'v', 'type' => 'video']]]);

    expect($withPhoto->hasImageField())->toBeTrue()
        ->and($withGallery->hasImageField())->toBeTrue()
        ->and($textOnly->hasImageField())->toBeFalse()
        ->and($videoOnly->hasImageField())->toBeFalse();
});
