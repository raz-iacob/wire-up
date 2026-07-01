<?php

declare(strict_types=1);

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
