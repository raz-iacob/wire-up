<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

final readonly class EnableTwoFactorAction
{
    public function __construct(private EnableTwoFactorAuthentication $enable) {}

    public function handle(User $user, bool $force = false): void
    {
        ($this->enable)($user, $force);
    }
}
