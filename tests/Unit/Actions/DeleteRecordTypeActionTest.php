<?php

declare(strict_types=1);

use App\Actions\DeleteRecordTypeAction;
use App\Models\RecordType;

it('deletes a record type', function (): void {
    $type = RecordType::factory()->create();

    resolve(DeleteRecordTypeAction::class)->handle($type);

    $this->assertModelMissing($type);
});
