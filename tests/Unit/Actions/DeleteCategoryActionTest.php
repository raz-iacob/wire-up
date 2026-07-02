<?php

declare(strict_types=1);

use App\Actions\CreateCategoryAction;
use App\Actions\DeleteCategoryAction;
use App\Models\Record;
use App\Models\RecordType;

it('deletes a category and detaches it from records', function (): void {
    $type = RecordType::factory()->create();
    $record = Record::factory()->create(['record_type_id' => $type->id]);
    $category = resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Temp']]);
    $category->records()->attach($record);

    resolve(DeleteCategoryAction::class)->handle($category);

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    $this->assertDatabaseMissing('categorizables', ['category_id' => $category->id]);
});
