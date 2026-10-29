<?php

declare(strict_types=1);

use App\Enums\FieldPlacement;
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

it('lists displayable fields excluding heading, overview and media', function (): void {
    $type = RecordType::factory()->create([
        'fields' => [
            ['key' => 'heading', 'type' => 'text'],
            ['key' => 'overview', 'type' => 'rich-text'],
            ['key' => 'current_price', 'type' => 'money'],
            ['key' => 'sku', 'type' => 'text'],
            ['key' => 'gallery', 'type' => 'media-gallery'],
        ],
    ]);

    expect(array_column($type->displayableFields(), 'key'))->toBe(['current_price', 'sku']);
});

it('uses a stored placement and falls back to how the page looked before placements existed', function (): void {
    $custom = RecordType::factory()->create(['key' => 'pc_builds', 'fields' => [
        ['key' => 'heading', 'type' => 'text'],
        ['key' => 'photo', 'type' => 'photo'],
        ['key' => 'cpu', 'type' => 'text'],
        ['key' => 'parts', 'type' => 'relationship'],
        ['key' => 'notes', 'type' => 'textarea', 'placement' => 'sidebar'],
        ['key' => 'odd', 'type' => 'text', 'placement' => 'nowhere'],
    ]]);
    $product = RecordType::factory()->create(['key' => 'product', 'fields' => [
        ['key' => 'gallery', 'type' => 'media-gallery'],
        ['key' => 'heading', 'type' => 'text'],
        ['key' => 'current_price', 'type' => 'money'],
        ['key' => 'sku', 'type' => 'text'],
    ]]);

    expect(array_map(fn (array $field): string => $custom->placementFor($field)->value, $custom->fields))
        ->toBe(['main', 'sidebar', 'hidden', 'main', 'sidebar', 'hidden'])
        ->and(array_map(fn (array $field): string => $product->placementFor($field)->value, $product->fields))
        ->toBe(['main', 'sidebar', 'sidebar', 'hidden']);
});

it('lists the fields placed in a column in blueprint order and tells which ones the owner hid', function (): void {
    $type = RecordType::factory()->create(['fields' => [
        ['key' => 'a', 'type' => 'text', 'placement' => 'sidebar'],
        ['key' => 'b', 'type' => 'text', 'placement' => 'main'],
        ['key' => 'c', 'type' => 'text', 'placement' => 'sidebar'],
        ['key' => 'd', 'type' => 'text', 'placement' => 'hidden'],
        ['key' => 'e', 'type' => 'text'],
    ]]);

    expect(array_column($type->fieldsPlacedIn(FieldPlacement::SIDEBAR), 'key'))->toBe(['a', 'c'])
        ->and(array_column($type->fieldsPlacedIn(FieldPlacement::HIDDEN), 'key'))->toBe(['d', 'e'])
        ->and($type->isHiddenByOwner($type->fields[3]))->toBeTrue()
        ->and($type->isHiddenByOwner($type->fields[4]))->toBeFalse();
});
