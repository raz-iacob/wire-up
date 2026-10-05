<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class InviteAdminAction
{
    public function __construct(
        private CreateUserAction $createUser,
        private SendAdminInviteAction $sendInvite,
    ) {}

    /**
     * @return array{user: User, status: string}
     */
    public function handle(User $inviter, string $name, string $email, Role $role): array
    {
        $user = DB::transaction(fn (): User => $this->createUser->handle([
            'name' => $name,
            'email' => $email,
            'role_id' => $role->id,
        ], Str::random(16)));

        return ['user' => $user, 'status' => $this->sendInvite->handle($inviter, $user)];
    }
}
