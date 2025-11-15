<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserLastAccessAction
{
    public function handle(User $user, ?string $userAgent, ?string $ip = null): void
    {
        if (is_null($user->last_seen_at) || $user->last_seen_at->diffInMinutes(now()) > 30) {
            $user->update([
                'last_seen_at' => now(),
                'last_ip' => $ip,
                'user_agent' => $userAgent,
            ]);
        }
    }
}
