<?php

declare(strict_types=1);

use App\Actions\UpdateUserLastAccessAction;
use App\Models\User;

it('stamps last access for a user who has never been seen', function (): void {
    $user = User::factory()->create(['last_seen_at' => null]);

    (new UpdateUserLastAccessAction)->handle($user, 'Mozilla/5.0', '1.2.3.4');

    $fresh = $user->fresh();

    expect($fresh->last_seen_at)->not->toBeNull()
        ->and($fresh->last_ip)->toBe('1.2.3.4')
        ->and($fresh->user_agent)->toBe('Mozilla/5.0');
});

it('refreshes last access once it is stale', function (): void {
    $user = User::factory()->create(['last_seen_at' => now()->subMinutes(5), 'last_ip' => 'old']);

    (new UpdateUserLastAccessAction)->handle($user, 'Mozilla/5.0', '9.9.9.9');

    expect($user->fresh()->last_ip)->toBe('9.9.9.9');
});

it('does not rewrite last access within the throttle window', function (): void {
    $user = User::factory()->create(['last_seen_at' => now()->subSeconds(20), 'last_ip' => 'old']);

    (new UpdateUserLastAccessAction)->handle($user, 'Mozilla/5.0', '9.9.9.9');

    expect($user->fresh()->last_ip)->toBe('old');
});
