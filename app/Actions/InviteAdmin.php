<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Notifications\AdminInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class InviteAdmin
{
    public function handle(User $inviter, string $name, string $email): User
    {
        return DB::transaction(function () use ($inviter, $name, $email): User {

            $user = app(CreateUser::class)->handle([
                'name' => $name,
                'email' => $email,
                'admin' => true,
            ], Str::random(16));

            Password::sendResetLink(['email' => $email],
                function (User $User, string $token) use ($inviter): void {
                    $User->notify(new AdminInvite($inviter->name, $token));
                }
            );

            return $user;
        });
    }
}
