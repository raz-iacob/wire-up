<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Record;
use App\Models\RecordType;
use App\Models\Slug;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRecordTypeAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(RecordType $recordType, array $attributes): void
    {
        $becomesAdminOnly = ! $recordType->admin_only && (bool) ($attributes['admin_only'] ?? false);
        $previousPrefix = $recordType->slug_prefix;

        DB::transaction(function () use ($recordType, $attributes, $previousPrefix): void {
            $recordType->update($attributes);

            if ($recordType->slug_prefix !== $previousPrefix) {
                Slug::query()
                    ->where('sluggable_type', new Record()->getMorphClass())
                    ->whereIn('sluggable_id', $recordType->records()->select('id'))
                    ->update(['base_path' => $recordType->slug_prefix]);
            }
        });

        if ($becomesAdminOnly) {
            new MakeDocumentsPrivateAction()->handle($recordType);
        }
    }
}
