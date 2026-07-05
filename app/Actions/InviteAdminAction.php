<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use App\Notifications\AdminInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final readonly class InviteAdminAction
{
    public function __construct(private CreateUserAction $createUser) {}

    public function handle(User $inviter, string $name, string $email, Role $role): User
    {
        return DB::transaction(function () use ($inviter, $name, $email, $role): User {

            $user = $this->createUser->handle([
                'name' => $name,
                'email' => $email,
                'role_id' => $role->id,
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
