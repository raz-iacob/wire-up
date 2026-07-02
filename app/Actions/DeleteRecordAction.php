<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Record;
use Illuminate\Support\Facades\DB;

final readonly class DeleteRecordAction
{
    public function handle(Record $record): void
    {
        DB::transaction(fn () => $record->delete());
    }
}
