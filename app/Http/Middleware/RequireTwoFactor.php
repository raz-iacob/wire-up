<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\SignInWithPasskeyAction;
use App\Models\User;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireTwoFactor
{
    public static function isSatisfiedFor(User $user): bool
    {
        return ! SettingsService::current()->requiresTwoFactor()
            || $user->hasEnabledTwoFactorAuthentication()
            || session(SignInWithPasskeyAction::SESSION_KEY) === true;
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $user->canAccessAdmin()
            || self::isSatisfiedFor($user)
            || $request->routeIs('admin.account-security')) {
            return $next($request);
        }

        return redirect(route('admin.account-security'));
    }
}
