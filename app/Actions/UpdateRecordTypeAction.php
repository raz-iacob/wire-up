<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RecordType;

final readonly class UpdateRecordTypeAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(RecordType $recordType, array $attributes): void
    {
        $becomesAdminOnly = ! $recordType->admin_only && (bool) ($attributes['admin_only'] ?? false);

        $recordType->update($attributes);

        if ($becomesAdminOnly) {
            new MakeDocumentsPrivateAction()->handle($recordType);
        }
    }
}
