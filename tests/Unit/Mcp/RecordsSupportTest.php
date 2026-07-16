<?php

declare(strict_types=1);

use App\Mcp\Support\Records;
use App\Models\RecordType;

it('skips fields with an unrecognized type when cleaning data', function (): void {
    $type = RecordType::factory()->make([
        'fields' => [
            ['key' => 'ghost', 'type' => 'bogus', 'label' => ['en' => 'Ghost'], 'translatable' => false],
            ['key' => 'name', 'type' => 'text', 'label' => ['en' => 'Name'], 'translatable' => false],
        ],
    ]);

    $clean = Records::cleanData($type, ['ghost' => 'x', 'name' => 'Kept'], 'en');

    expect($clean)->toBe(['name' => 'Kept']);
});

it('merges translatable values into existing locales', function (): void {
    $type = RecordType::factory()->make([
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'label' => ['en' => 'Heading'], 'translatable' => true],
        ],
    ]);

    $clean = Records::cleanData($type, ['heading' => 'Nieuw'], 'nl', ['heading' => ['en' => 'Old']]);

    expect($clean['heading'])->toBe(['en' => 'Old', 'nl' => 'Nieuw']);
});

it('coerces numeric and boolean field values', function (): void {
    $type = RecordType::factory()->make([
        'fields' => [
            ['key' => 'price', 'type' => 'money', 'label' => ['en' => 'Price'], 'translatable' => false],
            ['key' => 'stock', 'type' => 'number', 'label' => ['en' => 'Stock'], 'translatable' => false],
            ['key' => 'active', 'type' => 'boolean', 'label' => ['en' => 'Active'], 'translatable' => false],
            ['key' => 'note', 'type' => 'text', 'label' => ['en' => 'Note'], 'translatable' => false],
        ],
    ]);

    $clean = Records::cleanData($type, [
        'price' => '49.99',
        'stock' => 'not-a-number',
        'active' => 1,
        'note' => '',
    ], 'en');

    expect($clean['price'])->toBe(49.99)
        ->and($clean['stock'])->toBeNull()
        ->and($clean['active'])->toBeTrue()
        ->and($clean['note'])->toBeNull();
});
