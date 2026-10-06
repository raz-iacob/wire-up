<?php

declare(strict_types=1);

use App\Services\RecordTypePresets;

it('adds the missing shop fields and keeps the existing ones in place', function (): void {
    $fields = RecordTypePresets::withSellableFields([
        ['key' => 'heading', 'type' => 'text', 'translatable' => true],
        ['key' => 'current_price', 'type' => 'money', 'translatable' => true],
    ]);

    expect(array_column($fields, 'key'))->toBe(['heading', 'current_price', 'billing', 'shippable'])
        ->and($fields[0]['translatable'])->toBeTrue()
        ->and($fields[1]['translatable'])->toBeFalse()
        ->and($fields[2]['type'])->toBe('select')
        ->and($fields[2]['options'])->toBe(RecordTypePresets::BILLING_OPTIONS)
        ->and($fields[2]['translatable'])->toBeFalse()
        ->and($fields[3]['type'])->toBe('boolean');
});

it('makes the product preset sellable with its shop fields', function (): void {
    $preset = RecordTypePresets::find('product');

    expect($preset['sellable'])->toBeTrue()
        ->and(array_column($preset['fields'], 'key'))->toContain('current_price', 'billing', 'shippable');
});
