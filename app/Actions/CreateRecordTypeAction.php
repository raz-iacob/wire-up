<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RecordType;

final readonly class CreateRecordTypeAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): RecordType
    {
        $attributes['position'] ??= (int) RecordType::query()->max('position') + 1;

        return RecordType::query()->create($attributes);
    }
}
