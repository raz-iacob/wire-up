<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Actions\DeleteRecordAction;
use App\Actions\UpdateRecordAction;
use App\Models\RecordType;

it('deletes a record and cascades its slugs, translations and blocks', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'products']);
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'Gone']);
    resolve(UpdateRecordAction::class)->handle($record, [
        'blocks' => ['new-1' => ['id' => 'new-1', 'type' => 'rich-text', 'position' => 0, 'content' => []]],
    ]);

    $id = $record->id;

    resolve(DeleteRecordAction::class)->handle($record);

    $this->assertModelMissing($record);
    $this->assertDatabaseMissing('slugs', ['sluggable_type' => 'record', 'sluggable_id' => $id]);
    $this->assertDatabaseMissing('translations', ['translatable_type' => 'record', 'translatable_id' => $id]);
    $this->assertDatabaseMissing('blocks', ['blockable_type' => 'record', 'blockable_id' => $id]);
});
