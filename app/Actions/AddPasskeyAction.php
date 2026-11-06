<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Services\ActivityLog;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\Exception\WebauthnException;
use Webauthn\PublicKeyCredentialCreationOptions;

final readonly class AddPasskeyAction
{
    public function __construct(private ReadPasskeyCredentialAction $read, private StorePasskey $store) {}

    /**
     * @param  array<array-key, mixed>  $credential
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $name, array $credential): Passkey
    {
        $options = Session::pull(StartPasskeyRegistrationAction::SESSION_KEY);

        if (! is_string($options)) {
            throw ReadPasskeyCredentialAction::failed();
        }

        $publicKeyCredential = $this->read->handle($credential);

        try {
            $passkey = ($this->store)($user, $name === '' ? __('Passkey') : $name, $publicKeyCredential, WebAuthn::fromJson($options, PublicKeyCredentialCreationOptions::class));
        } catch (WebauthnException) {
            throw ReadPasskeyCredentialAction::failed();
        }

        if ($name === '' && $passkey->authenticator !== null) {
            $passkey->update(['name' => $passkey->authenticator]);
        }

        ActivityLog::record(ActivityEvent::PASSKEY_ADDED, $passkey->name);

        return $passkey;
    }
}
