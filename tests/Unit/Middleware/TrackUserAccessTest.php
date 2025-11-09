<?php

declare(strict_types=1);

use App\Http\Middleware\TrackUserAccess;
use App\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('tracks user access when user is logged in', function (): void {
    $this->travelTo($now = now()->utc());
    $middleware = new TrackUserAccess();
    $user = User::factory()->create([
        'email' => 'test@cinevee.com',
    ])->refresh();

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);
    $this->withoutDefer();

    $response = $middleware->handle($request, fn (): ResponseFactory|Response => response('OK'));

    expect($response->getStatusCode())->toBe(200)
        ->and($user->last_seen_at->toDateTimeString())->toEqual($now->toDateTimeString())
        ->and($user->last_ip)->toBe('127.0.0.1')
        ->and($user->user_agent)->toBe($request->header('User-Agent'));
});
