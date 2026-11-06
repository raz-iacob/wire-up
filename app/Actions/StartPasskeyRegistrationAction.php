<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Session;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Support\WebAuthn;

final readonly class StartPasskeyRegistrationAction
{
    public const string SESSION_KEY = 'passkey.registration_options';

    public function __construct(private GenerateRegistrationOptions $generate) {}

    /**
     * @return array<array-key, mixed>
     */
    public function handle(User $user): array
    {
        $options = ($this->generate)($user);

        Session::put(self::SESSION_KEY, WebAuthn::toJson($options));

        return WebAuthn::toBrowserArray($options);
    }
}
