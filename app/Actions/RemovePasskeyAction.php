<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Services\ActivityLog;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Passkey;

final readonly class RemovePasskeyAction
{
    public function __construct(private DeletePasskey $delete) {}

    public function handle(User $user, Passkey $passkey): void
    {
        ($this->delete)($user, $passkey);

        ActivityLog::record(ActivityEvent::PASSKEY_REMOVED, $passkey->name);
    }
}
