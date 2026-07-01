<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RecordType;

final readonly class DeleteRecordTypeAction
{
    public function handle(RecordType $recordType): void
    {
        $recordType->delete();
    }
}
