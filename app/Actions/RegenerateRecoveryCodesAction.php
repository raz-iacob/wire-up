<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

final readonly class RegenerateRecoveryCodesAction
{
    public function __construct(private GenerateNewRecoveryCodes $generate) {}

    public function handle(User $user): void
    {
        ($this->generate)($user);
    }
}
