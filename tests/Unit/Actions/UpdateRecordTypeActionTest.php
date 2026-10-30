<?php

declare(strict_types=1);

use App\Actions\UpdateRecordTypeAction;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;

it('updates a record type', function (): void {
    $type = RecordType::factory()->create([
        'name' => 'Old',
        'icon' => 'rectangle-stack',
    ]);

    resolve(UpdateRecordTypeAction::class)->handle($type, [
        'name' => 'New',
        'icon' => 'star',
    ]);

    $fresh = $type->fresh();

    expect($fresh->name)->toBe('New')
        ->and($fresh->icon)->toBe('star');
});

it('moves the records to the new web address when the prefix changes', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'products']);
    $record = Record::factory()->for($type)->create();
    $record->slugs()->create(['locale' => 'en', 'slug' => 'lamp', 'base_path' => 'products']);
    $page = Page::factory()->create();
    $page->slugs()->create(['locale' => 'en', 'slug' => 'about-us', 'base_path' => '']);

    resolve(UpdateRecordTypeAction::class)->handle($type, ['slug_prefix' => 'shop']);

    expect($record->slugs()->value('base_path'))->toBe('shop')
        ->and($page->slugs()->value('base_path'))->toBe('')
        ->and(Record::query()->forSlug('lamp', 'en', 'shop')->exists())->toBeTrue();
});
