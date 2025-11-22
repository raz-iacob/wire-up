<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

final class LocaleRedirect
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $params = explode('/', $request->getPathInfo());

        array_shift($params);

        if (count($params) > 0) {
            $locale = $params[0];
            $localization = app('localization');
            if ($localization->isActiveLocale($locale) && $locale === config()->string('app.locale', 'en')) {
                $redirection = $localization->stripDefaultLocale($request->getPathInfo());
                app(SessionManager::class)->reflash();

                return new RedirectResponse($redirection, 302, ['Vary' => 'Accept-Language']);
            }
        }

        return $next($request);
    }
}
