<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use SensitiveParameter;

final readonly class VerifyTwoFactorCodeAction
{
    public function __construct(private TwoFactorAuthenticationProvider $provider) {}

    public function handle(User $user, #[SensitiveParameter] string $code = '', #[SensitiveParameter] string $recoveryCode = ''): bool
    {
        if ($recoveryCode !== '') {
            return $this->useRecoveryCode($user, $recoveryCode);
        }

        return $code !== '' && $this->verifyTimedCode($user, $code);
    }

    private function verifyTimedCode(User $user, #[SensitiveParameter] string $code): bool
    {
        $secret = $user->two_factor_secret;

        if ($secret === null) {
            return false;
        }

        return (bool) $this->provider->verify((string) Fortify::currentEncrypter()->decrypt($secret), $code);
    }

    private function useRecoveryCode(User $user, #[SensitiveParameter] string $recoveryCode): bool
    {
        if ($user->two_factor_recovery_codes === null) {
            return false;
        }

        /** @var array<int, string> $stored */
        $stored = $user->recoveryCodes();

        $match = array_find($stored, fn (string $candidate): bool => hash_equals($candidate, $recoveryCode));

        if ($match === null) {
            return false;
        }

        $user->replaceRecoveryCode($match);

        return true;
    }
}
