<?php

declare(strict_types=1);

use App\Services\LucideIconService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const LUCIDE_SVG = <<<'SVG'
<!-- @license lucide-static v1.24.0 - ISC -->
<svg class="lucide lucide-search" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
  <path d="m21 21-4.34-4.34" />
  <circle cx="11" cy="11" r="8" />
</svg>
SVG;

it('fetches, sanitizes and caches a valid icon', function (): void {
    Http::fake(['unpkg.com/*' => Http::response(LUCIDE_SVG)]);

    $svg = resolve(LucideIconService::class)->svg('search');

    expect($svg)->toContain('<svg')
        ->and($svg)->toContain('circle')
        ->and(Cache::get('lucide:svg:1.24.0:search'))->toBe($svg);

    Http::assertSentCount(1);
});

it('serves a cached icon without a second request', function (): void {
    Cache::forever('lucide:svg:1.24.0:search', '<svg>cached</svg>');
    Http::fake();

    expect(resolve(LucideIconService::class)->svg('search'))->toBe('<svg>cached</svg>');

    Http::assertNothingSent();
});

it('normalizes the name before lookup', function (): void {
    Http::fake(['unpkg.com/*' => Http::response(LUCIDE_SVG)]);

    resolve(LucideIconService::class)->svg('  SEARCH  ');

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/icons/search.svg'));
});

it('rejects an invalid name without any request', function (string $name): void {
    Http::fake();

    expect(resolve(LucideIconService::class)->svg($name))->toBeNull();

    Http::assertNothingSent();
})->with(['spaces in name', 'Upper_Case', 'slash/name', '']);

it('returns null when the icon does not exist', function (): void {
    Http::fake(['unpkg.com/*' => Http::response('Not found', 404)]);

    expect(resolve(LucideIconService::class)->svg('definitelynotanicon'))->toBeNull()
        ->and(Cache::has('lucide:svg:1.24.0:definitelynotanicon'))->toBeFalse();
});

it('returns null when the response is not an svg', function (): void {
    Http::fake(['unpkg.com/*' => Http::response('just some text')]);

    expect(resolve(LucideIconService::class)->svg('search'))->toBeNull();
});

it('returns null when the connection fails', function (): void {
    Http::fake(fn () => throw new ConnectionException('offline'));

    expect(resolve(LucideIconService::class)->svg('search'))->toBeNull();
});
