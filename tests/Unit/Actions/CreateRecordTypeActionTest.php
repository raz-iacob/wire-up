<?php

declare(strict_types=1);

use App\Actions\CreateRecordTypeAction;
use App\Models\RecordType;

it('creates a record type and assigns the next position', function (): void {
    RecordType::factory()->create(['position' => 5]);

    $type = resolve(CreateRecordTypeAction::class)->handle([
        'key' => 'product',
        'slug_prefix' => 'products',
        'icon' => 'shopping-bag',
        'name' => 'Product',
        'fields' => [],
    ]);

    expect($type->position)->toBe(6);

    $this->assertDatabaseHas('record_types', ['key' => 'product', 'slug_prefix' => 'products']);
});

it('respects an explicit position', function (): void {
    $type = resolve(CreateRecordTypeAction::class)->handle([
        'key' => 'event',
        'slug_prefix' => 'events',
        'name' => 'Event',
        'position' => 3,
    ]);

    expect($type->position)->toBe(3);
});
