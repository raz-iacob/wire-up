<?php

declare(strict_types=1);

use App\Actions\DeleteRecordTypeAction;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Role;

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

it('removes the deleted content type from every role', function (): void {
    $type = RecordType::factory()->create(['key' => 'gadget']);
    $role = Role::factory()->create(['abilities' => ['pages.view', 'records.gadget.view', 'records.gadget.edit', 'records.gadgets.view']]);

    resolve(DeleteRecordTypeAction::class)->handle($type);

    expect($role->refresh()->abilities)->toBe(['pages.view', 'records.gadgets.view']);
});
