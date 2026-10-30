<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\Settings;
use App\Services\ActivityLog;

final readonly class UpdateSettingsAction
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(array $values): void
    {
        $changed = Settings::set($values);

        if ($changed !== []) {
            ActivityLog::record(ActivityEvent::SETTINGS_UPDATED, properties: ['keys' => $changed]);
        }
    }
}
