<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Support\Facades\DB;

final readonly class CreateRecordAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(RecordType $recordType, array $attributes): Record
    {
        return DB::transaction(function () use ($recordType, $attributes): Record {
            $attributes['record_type_id'] = $recordType->id;
            $attributes['metadata'] = [
                ...($attributes['metadata'] ?? []),
                'published_locales' => $attributes['metadata']['published_locales']
                    ?? [resolve('localization')->getDefaultLocale()],
            ];

            $record = Record::query()->create($attributes);
            $record->setRelation('recordType', $recordType);
            $record->setSlugs();

            return $record;
        });
    }
}
