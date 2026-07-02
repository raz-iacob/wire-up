<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Models\RecordType;

it('creates a record with a namespaced slug and default published locale', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'products']);

    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'Blue Shoes']);

    expect($record->record_type_id)->toBe($type->id)
        ->and($record->title)->toBe('Blue Shoes')
        ->and($record->slug)->toBe('blue-shoes')
        ->and($record->published_locales)->toBe([config()->string('app.default_locale', 'en')]);

    $this->assertDatabaseHas('slugs', [
        'slug' => 'blue-shoes',
        'base_path' => 'products',
        'sluggable_type' => 'record',
        'sluggable_id' => $record->id,
    ]);
});
