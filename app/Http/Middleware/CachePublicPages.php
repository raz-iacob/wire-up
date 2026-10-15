<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PageCache;
use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

final readonly class CachePublicPages
{
    public function __construct(private PageCache $cache) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cache->cacheable($request)) {
            return $next($request);
        }

        $hit = $this->cache->get($request);

        if ($hit !== null) {
            if (str_contains($hit['type'], 'text/html')) {
                Livewire::forceAssetInjection();
            }

            return response($hit['body'], 200, array_filter([
                'Content-Type' => $hit['type'],
                'Vary' => $hit['vary'],
                'X-Page-Cache' => 'hit',
            ]));
        }

        $response = $next($request);

        $this->cache->put($request, $response);
        $response->headers->set('X-Page-Cache', 'miss');

        return $response;
    }
}
