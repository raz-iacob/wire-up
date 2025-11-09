<?php

declare(strict_types=1);

use App\Models\User;

test('to array', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            'email_verified_at',
            'details',
            'admin',
            'active',
            'locale',
            'last_seen_at',
            'user_agent',
            'last_ip',
            'created_at',
            'updated_at',
        ]);
});

it('has initials attribute', function (): void {
    $user = User::factory()->create(['name' => 'John Doe']);

    expect($user->initials)->toBe('JD');

    $user->name = 'Alice';
    expect($user->initials)->toBe('A');

    $user->name = 'Bob Charles Dylan';
    expect($user->initials)->toBe('BC');
});
