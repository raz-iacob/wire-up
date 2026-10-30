<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\RecordType;
use App\Services\ActivityLog;
use App\Services\RedirectService;

final readonly class CreateRecordTypeAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): RecordType
    {
        $attributes['position'] ??= (int) RecordType::query()->max('position') + 1;

        $recordType = RecordType::query()->create($attributes);

        new GrantRecordTypeAbilitiesAction()->handle($recordType->key);

        resolve(RedirectService::class)->releasePrefix($recordType->slug_prefix);

        ActivityLog::record(ActivityEvent::RECORD_TYPE_CREATED, $recordType);

        return $recordType;
    }
}
