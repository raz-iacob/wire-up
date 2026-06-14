<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Page;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectHomepageSlug
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        $home = SettingsService::current()->homePage();

        if (is_string($slug) && $home instanceof Page && $home->slug === $slug) {
            return to_route('home', status: 301);
        }

        return $next($request);
    }
}
