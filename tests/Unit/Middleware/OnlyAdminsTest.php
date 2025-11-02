<?php

declare(strict_types=1);

use App\Http\Middleware\OnlyAdmins;
use App\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('redirects guests to /login', function (): void {
    $middleware = new OnlyAdmins;
    $request = Request::create('/admin/');

    $response = $middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(url('/login'));
});

it('redirects non-admin users to /', function (): void {
    $middleware = new OnlyAdmins;
    $request = Request::create('/admin/');
    $request->setUserResolver(fn () => User::factory()->create(['admin' => false]));

    $response = $middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(url('/'));
});

it('allows admin users to proceed', function (): void {
    $middleware = new OnlyAdmins;
    $request = Request::create('/admin/');
    $request->setUserResolver(fn () => User::factory()->create(['admin' => true]));

    $response = $middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

    expect($response->getContent())->toBe('OK');
});
