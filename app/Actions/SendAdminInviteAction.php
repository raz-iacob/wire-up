<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Notifications\AdminInvite;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Password;

final readonly class SendAdminInviteAction
{
    public const string NOT_CONFIGURED = 'invite.mail-not-configured';

    public function handle(User $inviter, User $invitee): string
    {
        if (! SettingsService::current()->mailConfigured()) {
            return self::NOT_CONFIGURED;
        }

        $status = Password::broker('invitations')->sendResetLink(['email' => $invitee->email],
            function (User $user, string $token) use ($inviter): void {
                $user->notify(new AdminInvite($inviter->name, $token));
            }
        );

        if ($status === Password::RESET_LINK_SENT) {
            $invitee->forceFill(['invited_at' => now()])->save();
        }

        return $status;
    }
}
