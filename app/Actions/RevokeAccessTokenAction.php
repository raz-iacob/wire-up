<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\AccessToken;
use App\Services\ActivityLog;

final readonly class RevokeAccessTokenAction
{
    public function handle(AccessToken $token): void
    {
        $token->delete();

        ActivityLog::record(ActivityEvent::ACCESS_TOKEN_REVOKED, $token->name);
    }
}
