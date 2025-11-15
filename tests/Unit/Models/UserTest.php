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
            'photo',
            'metadata',
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

    $user->fill(['name' => 'Alice']);
    expect($user->initials)->toBe('A');

    $user->fill(['name' => 'Bob Charles Dylan']);
    expect($user->initials)->toBe('BC');
});

it('has photo_url attribute', function (): void {
    $user = User::factory()->create(['photo' => null]);
    expect($user->photo_url)->toBeNull();

    $user = User::factory()->create(['photo' => 'avatars/user-123.jpg']);
    expect($user->photo_url)
        ->toBeString()
        ->toContain('avatars/user-123.jpg');
});
