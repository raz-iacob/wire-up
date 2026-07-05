<?php

declare(strict_types=1);

use App\Models\User;

it('reports admin access from the role', function (): void {
    expect(User::factory()->owner()->create()->canAccessAdmin())->toBeTrue();
    expect(User::factory()->member()->create()->canAccessAdmin())->toBeFalse();
});

it('resolves abilities through the role and owner bypass', function (): void {
    expect(User::factory()->editor()->create()->hasAbility('pages.view'))->toBeTrue();
    expect(User::factory()->editor()->create()->hasAbility('users.edit'))->toBeFalse();
    expect(User::factory()->owner()->create()->hasAbility('settings.edit'))->toBeTrue();
    expect(User::factory()->member()->create()->hasAbility('pages.view'))->toBeFalse();
});

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
            'role_id',
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
