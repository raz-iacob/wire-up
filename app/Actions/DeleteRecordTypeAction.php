<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RecordType;
use RuntimeException;

final readonly class DeleteRecordTypeAction
{
    public function handle(RecordType $recordType): void
    {
        throw_if($recordType->isInUse(), RuntimeException::class, 'Cannot delete a content type that still has records.');

        $recordType->delete();
    }
}
