<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AccessToken;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateMcpToken
{
    public const int FAILURES_PER_MINUTE = 20;

    public const int REQUESTS_PER_MINUTE = 120;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(SettingsService::current()->remoteMcpEnabled(), 404);

        if (app()->isProduction() && ! $request->secure()) {
            return $this->refuse(__('Connect to this site over https://.'), 403);
        }

        $failures = 'mcp-auth-failures:'.$request->ip();

        if (RateLimiter::tooManyAttempts($failures, self::FAILURES_PER_MINUTE)) {
            return $this->tooManyRequests(RateLimiter::availableIn($failures));
        }

        $token = $this->resolve($request);

        if (! $token instanceof AccessToken) {
            RateLimiter::hit($failures);

            return $this->refuse(__('This token isn’t valid. Make a new one under Account → Security.'), 401);
        }

        $requests = 'mcp-token:'.$token->id;

        if (RateLimiter::tooManyAttempts($requests, self::REQUESTS_PER_MINUTE)) {
            return $this->tooManyRequests(RateLimiter::availableIn($requests));
        }

        RateLimiter::hit($requests);

        Auth::setUser($token->user);
        $request->attributes->set(AccessToken::REQUEST_ATTRIBUTE, $token);

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->update(['last_used_at' => now()]);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?AccessToken
    {
        $plainText = $request->bearerToken();
        $token = is_string($plainText) ? AccessToken::findByPlainText($plainText) : null;

        if (! $token instanceof AccessToken || $token->isExpired()) {
            return null;
        }

        $user = $token->user;

        return $user->active && $user->canAccessAdmin() && Gate::forUser($user)->allows('mcp.use') ? $token : null;
    }

    private function refuse(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }

    private function tooManyRequests(int $seconds): JsonResponse
    {
        return response()->json(['error' => __('Too many requests. Try again in :seconds seconds.', ['seconds' => $seconds])], 429)
            ->header('Retry-After', (string) $seconds);
    }
}
