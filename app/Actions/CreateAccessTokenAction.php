<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\TokenAccess;
use App\Models\AccessToken;
use App\Models\User;
use App\Services\ActivityLog;
use App\Services\Impersonation;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CreateAccessTokenAction
{
    /**
     * @return array{token: AccessToken, plainText: string}
     *
     * @throws AuthorizationException
     */
    public function handle(User $user, string $name, TokenAccess $access, int $days): array
    {
        throw_if(Impersonation::impersonatorId() !== null, AuthorizationException::class, __('Tokens can’t be made while you are signed in as someone else.'));

        $plainText = AccessToken::generate();

        $token = $user->accessTokens()->create([
            'name' => $name,
            'token_hash' => AccessToken::hash($plainText),
            'access' => $access,
            'expires_at' => now()->addDays($days),
        ]);

        ActivityLog::record(ActivityEvent::ACCESS_TOKEN_CREATED, $token->name, ['access' => $access->value]);

        return ['token' => $token, 'plainText' => $plainText];
    }
}
