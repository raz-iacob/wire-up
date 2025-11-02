<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class OnlyAdmins
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return match (true) {
            ! $request->user() => redirect('/login'),
            ! $request->user()->admin => redirect('/'),
            default => $next($request),
        };
    }
}
