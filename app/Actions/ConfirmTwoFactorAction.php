<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use SensitiveParameter;

final readonly class ConfirmTwoFactorAction
{
    public function __construct(private ConfirmTwoFactorAuthentication $confirm) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, #[SensitiveParameter] string $code): void
    {
        try {
            ($this->confirm)($user, $code);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'code' => __('That code is not correct. Check your authenticator app and try again.'),
            ]);
        }
    }
}
