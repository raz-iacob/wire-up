<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class Impersonation
{
    public const string SESSION_KEY = 'impersonator_id';

    public static function active(): bool
    {
        return self::impersonatorId() !== null;
    }

    public static function impersonatorId(): ?int
    {
        $id = session()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public static function canStart(?User $actor, User $target): bool
    {
        return $actor instanceof User
            && ! self::active()
            && $actor->can('users.edit')
            && $target->active
            && ! $target->canAccessAdmin();
    }

    public static function start(?User $actor, User $target): void
    {
        abort_unless(self::canStart($actor, $target), 403);

        ActivityLog::record(ActivityEvent::IMPERSONATION_STARTED, $target, userId: $actor?->id);

        session()->put(self::SESSION_KEY, $actor?->id);

        Auth::login($target);
    }

    public static function stop(User $member): string
    {
        $impersonator = User::query()->find(self::impersonatorId());

        if (! $impersonator instanceof User || ! $impersonator->active || ! $impersonator->canAccessAdmin()) {
            session()->invalidate();
            session()->regenerateToken();
            Auth::forgetUser();

            return route('login');
        }

        Auth::login($impersonator);

        session()->forget(self::SESSION_KEY);

        ActivityLog::record(ActivityEvent::IMPERSONATION_STOPPED, $member, userId: $impersonator->id);

        return route('admin.users-edit', $member);
    }
}
