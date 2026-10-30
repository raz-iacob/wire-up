<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Services\ActivityLog;
use App\Services\Impersonation;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

final readonly class RecordSignInActivity
{
    public function handle(Login|Logout|Failed $event): void
    {
        if (Impersonation::active()) {
            return;
        }

        $userId = $event->user?->getAuthIdentifier();
        $userId = is_numeric($userId) ? (int) $userId : null;

        match (true) {
            $event instanceof Login => ActivityLog::record(ActivityEvent::SIGNED_IN, userId: $userId),
            $event instanceof Logout => ActivityLog::record(ActivityEvent::SIGNED_OUT, userId: $userId),
            default => ActivityLog::record(ActivityEvent::SIGN_IN_FAILED, (string) ($event->credentials['email'] ?? ''), userId: $userId),
        };
    }
}
