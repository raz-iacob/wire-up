<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLog;

final readonly class UpdateUserAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): void
    {
        $email = $attributes['email'] ?? null;

        $user->fill([
            ...$attributes,
            ...$user->email === $email ? [] : ['email_verified_at' => null],
        ]);

        $previousRoleId = $user->getOriginal('role_id');
        $roleChanged = $user->isDirty('role_id');
        $activeChanged = $user->isDirty('active');

        $user->save();

        if ($roleChanged) {
            ActivityLog::record(ActivityEvent::USER_ROLE_CHANGED, $user, [
                'from' => Role::query()->whereKey($previousRoleId)->value('name'),
                'to' => Role::query()->whereKey($user->role_id)->value('name'),
            ]);
        }

        if ($activeChanged) {
            ActivityLog::record($user->active ? ActivityEvent::USER_ENABLED : ActivityEvent::USER_DISABLED, $user);
        }
    }
}
