<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\UpdateUserLastAccessAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TrackUserAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            defer(fn () => (new UpdateUserLastAccessAction)->handle($user, $request->header('User-Agent'), $request->ip()));
        }

        return $next($request);
    }
}
