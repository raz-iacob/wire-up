<?php

declare(strict_types=1);

use App\Http\Middleware\LocaleRedirect;
use App\Models\Locale;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

it('skips non-get requests', function (): void {
    $middleware = new LocaleRedirect;
    $request = Request::create('/en/', 'POST');

    $response = $middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

    expect($response->getContent())->toBe('OK');
});

it('redirects when default locale is in path', function (): void {
    $middleware = new LocaleRedirect;
    $request = Request::create('/en/login');

    $response = $middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(url('/login'));
});

it('does not redirect for non-default locale in path', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    Cache::forget('site-locales');

    $middleware = new LocaleRedirect;
    $request = Request::create('/nl/login');

    $response = $middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

    expect($response->getContent())->toBe('OK')
        ->and($request->getPathInfo())->toBe('/nl/login');
});

it('does not redirect when no locale in path', function (): void {
    $middleware = new LocaleRedirect;
    $request = Request::create('/login');

    $response = $middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

    expect($response->getContent())->toBe('OK')
        ->and($request->getPathInfo())->toBe('/login');
});

it('does not redirect for inactive locale', function (): void {
    $middleware = new LocaleRedirect;
    $request = Request::create('/fr/login');

    $response = $middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

    expect($response->getContent())->toBe('OK')
        ->and($request->getPathInfo())->toBe('/fr/login');
});
