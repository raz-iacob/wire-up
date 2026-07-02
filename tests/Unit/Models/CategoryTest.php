<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;

it('resolves its name via translations', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Featured']]);

    expect($category->name)->toBe('Featured');
});

it('is attached to records and pages polymorphically', function (): void {
    $type = RecordType::factory()->create();
    $category = Category::factory()->create();
    $record = Record::factory()->create(['record_type_id' => $type->id]);
    $page = Page::factory()->create();

    $record->categories()->attach($category);
    $page->categories()->attach($category);

    expect($category->records)->toHaveCount(1)
        ->and($category->records->first()->id)->toBe($record->id)
        ->and($category->pages)->toHaveCount(1)
        ->and($category->pages->first()->id)->toBe($page->id)
        ->and($record->categories)->toHaveCount(1)
        ->and($page->categories)->toHaveCount(1);
});
