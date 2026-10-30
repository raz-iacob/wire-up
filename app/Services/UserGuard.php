<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class UserGuard
{
    public function canManage(?User $actor, User $target): bool
    {
        return ! ($target->role->bypass ?? false) || ($actor->role->bypass ?? false);
    }

    public function canAssign(?User $actor, Role $role): bool
    {
        return ! $role->bypass || ($actor->role->bypass ?? false);
    }

    public function loginLockReason(?User $actor, User $target): ?string
    {
        if ($actor instanceof User && $target->is($actor)) {
            return __('You cannot lock yourself out.');
        }

        if ($target->active && ($target->role->bypass ?? false) && $this->isLastFullAccessUser($target)) {
            return __('The site must keep one full-access user who can sign in.');
        }

        return null;
    }

    public function isLastFullAccessUser(User $user): bool
    {
        return User::query()
            ->whereKeyNot($user->id)
            ->where('active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('bypass', true))
            ->doesntExist();
    }
}
