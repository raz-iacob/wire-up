<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Session;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Support\WebAuthn;

final readonly class StartPasskeySignInAction
{
    public const string SESSION_KEY = 'passkey.verification_options';

    public function __construct(private GenerateVerificationOptions $generate) {}

    /**
     * @return array<array-key, mixed>
     */
    public function handle(): array
    {
        $options = ($this->generate)();

        Session::put(self::SESSION_KEY, WebAuthn::toJson($options));

        return WebAuthn::toBrowserArray($options);
    }
}
