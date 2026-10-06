<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use SensitiveParameter;

final readonly class CreateUserPasswordAction
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function handle(array $credentials, #[SensitiveParameter] string $password): mixed
    {
        return Password::broker($this->brokerFor($credentials))->reset(
            $credentials,
            function (User $user) use ($password): void {
                $user->update([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ]);

                event(new PasswordReset($user));
            }
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function brokerFor(array $credentials): string
    {
        $email = $credentials['email'] ?? null;

        $isPendingInvitee = is_string($email) && User::query()
            ->where('email', $email)
            ->whereNotNull('invited_at')
            ->whereNull('last_seen_at')
            ->exists();

        return $isPendingInvitee ? 'invitations' : 'users';
    }
}
