<?php

declare(strict_types=1);

use App\Enums\FieldType;
use App\Services\RecordTypePresets;

it('returns the built-in presets in order', function (): void {
    expect(RecordTypePresets::keys())->toBe(['product', 'service', 'post', 'event', 'team-member', 'project', 'job']);
});

it('finds a preset by key and returns null otherwise', function (): void {
    expect(RecordTypePresets::find('product'))->toBeArray()
        ->and(RecordTypePresets::find('product')['slug_prefix'])->toBe('products')
        ->and(RecordTypePresets::find('missing'))->toBeNull();
});

it('builds every preset with valid field definitions', function (): void {
    foreach (RecordTypePresets::all() as $preset) {
        expect($preset)->toHaveKeys(['key', 'slug_prefix', 'icon', 'name', 'fields'])
            ->and($preset['name'])->toBeString();

        foreach ($preset['fields'] as $field) {
            expect($field)->toHaveKeys(['key', 'type', 'label', 'required', 'translatable', 'column', 'sortable', 'searchable', 'help', 'options'])
                ->and(FieldType::tryFrom($field['type']))->not->toBeNull();
        }
    }
});

it('applies sensible field defaults to presets', function (): void {
    $fieldsByKey = fn (string $preset): array => collect(RecordTypePresets::find($preset)['fields'])
        ->keyBy('key')
        ->all();

    $product = $fieldsByKey('product');
    expect($product['current_price']['column'])->toBeTrue()
        ->and($product['current_price']['sortable'])->toBeTrue()
        ->and($product['sku']['translatable'])->toBeFalse()
        ->and($product['sku']['searchable'])->toBeTrue();

    $event = $fieldsByKey('event');
    expect($event['location']['translatable'])->toBeFalse()
        ->and($event['starts_at']['sortable'])->toBeTrue()
        ->and($event['starts_at']['column'])->toBeTrue();
});
