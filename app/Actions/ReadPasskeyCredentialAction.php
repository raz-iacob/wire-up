<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;

final readonly class ReadPasskeyCredentialAction
{
    public static function failed(): ValidationException
    {
        return ValidationException::withMessages([
            'credential' => __('That passkey didn\'t work. Please try again.'),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $credential
     *
     * @throws ValidationException
     */
    public function handle(array $credential): PublicKeyCredential
    {
        try {
            return WebAuthn::fromJson(json_encode($credential, JSON_THROW_ON_ERROR), PublicKeyCredential::class);
        } catch (Throwable) {
            throw self::failed();
        }
    }
}
