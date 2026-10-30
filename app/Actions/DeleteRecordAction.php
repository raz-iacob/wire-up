<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\ActivityLog;
use Illuminate\Support\Facades\DB;

final readonly class DeleteRecordAction
{
    public function handle(Record $record): void
    {
        DB::transaction(function () use ($record): void {
            ActivityLog::record(ActivityEvent::RECORD_DELETED, $record);

            $this->unlink($record);

            $record->delete();
        });
    }

    private function unlink(Record $record): void
    {
        $record->loadMissing('recordType');

        foreach (RecordType::relationshipsTo($record->recordType->key) as $link) {
            $key = (string) $link['field']['key'];

            Record::query()
                ->where('record_type_id', $link['type']->id)
                ->whereJsonContains("data->{$key}", $record->id)
                ->get(['id', 'data'])
                ->each(function (Record $linking) use ($key, $record): void {
                    $data = is_array($linking->data) ? $linking->data : [];
                    $ids = array_values(array_filter((array) ($data[$key] ?? []), fn (mixed $id): bool => (int) $id !== $record->id));
                    $data[$key] = $ids === [] ? null : $ids;

                    Record::query()->whereKey($linking->id)->update(['data' => json_encode($data, JSON_THROW_ON_ERROR)]);
                });
        }
    }
}
