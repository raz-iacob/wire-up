<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RedirectService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServeRedirects
{
    public function __construct(private RedirectService $redirects) {}

    public function handle(Request $request, Closure $next): Response
    {
        $match = $request->isMethod('GET') || $request->isMethod('HEAD') ? $this->redirects->match($request) : null;

        if ($match === null) {
            return $next($request);
        }

        defer(fn () => $this->redirects->recordHit($match['id']));

        return new RedirectResponse($match['url'], $match['status']);
    }
}
