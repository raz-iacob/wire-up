<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireTwoFactor
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $user->canAccessAdmin()
            || ! SettingsService::current()->requiresTwoFactor()
            || $user->hasEnabledTwoFactorAuthentication()
            || $request->routeIs('admin.account-security')) {
            return $next($request);
        }

        return redirect(route('admin.account-security'));
    }
}
