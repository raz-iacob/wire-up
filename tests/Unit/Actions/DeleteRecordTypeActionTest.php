<?php

declare(strict_types=1);

use App\Actions\DeleteRecordTypeAction;
use App\Models\Record;
use App\Models\RecordType;

it('deletes a record type', function (): void {
    $type = RecordType::factory()->create();

    resolve(DeleteRecordTypeAction::class)->handle($type);

    $this->assertModelMissing($type);
});

it('refuses to delete a record type that still has records', function (): void {
    $type = RecordType::factory()->create();
    Record::factory()->create(['record_type_id' => $type->id]);

    expect(fn (): mixed => resolve(DeleteRecordTypeAction::class)->handle($type))
        ->toThrow(RuntimeException::class);

    $this->assertModelExists($type);
});
