<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\Exception\WebauthnException;
use Webauthn\PublicKeyCredentialRequestOptions;

final readonly class SignInWithPasskeyAction
{
    public const string SESSION_KEY = 'auth.passkey';

    public const int MAX_ATTEMPTS = 10;

    public function __construct(private ReadPasskeyCredentialAction $read, private VerifyPasskey $verify) {}

    /**
     * @param  array<array-key, mixed>  $credential
     *
     * @throws ValidationException
     */
    public function handle(array $credential, bool $remember = false): User
    {
        $this->ensureIsNotRateLimited();

        try {
            $user = $this->verifiedUser($credential);
        } catch (ValidationException $exception) {
            RateLimiter::hit($this->throttleKey());

            throw $exception;
        }

        RateLimiter::clear($this->throttleKey());

        Auth::login($user, $remember);
        Session::regenerate();
        Session::put(self::SESSION_KEY, true);

        return $user;
    }

    /**
     * @param  array<array-key, mixed>  $credential
     *
     * @throws ValidationException
     */
    private function verifiedUser(array $credential): User
    {
        $options = Session::pull(StartPasskeySignInAction::SESSION_KEY);

        if (! is_string($options)) {
            throw ReadPasskeyCredentialAction::failed();
        }

        $publicKeyCredential = $this->read->handle($credential);

        try {
            $passkey = ($this->verify)($publicKeyCredential, WebAuthn::fromJson($options, PublicKeyCredentialRequestOptions::class));
        } catch (WebauthnException) {
            throw ReadPasskeyCredentialAction::failed();
        }

        $user = $passkey->user;

        if (! $user instanceof User || ! $user->active) {
            throw ValidationException::withMessages(['credential' => __('auth.failed')]);
        }

        return $user;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'credential' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return 'passkey|'.request()->ip();
    }
}
