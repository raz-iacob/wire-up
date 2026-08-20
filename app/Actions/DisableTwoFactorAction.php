<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

final readonly class DisableTwoFactorAction
{
    public function __construct(private DisableTwoFactorAuthentication $disable) {}

    public function handle(User $user): void
    {
        ($this->disable)($user);
    }
}
