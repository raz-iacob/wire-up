<?php

declare(strict_types=1);

use App\Models\User;

it('logs out an admin and returns them to the login screen', function (): void {

    $user = User::factory()->admin()->create([
        'active' => true,
    ]);
    $this->be($user);

    $response = $this->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

it('logs out a member and sends them home', function (): void {

    $user = User::factory()->member()->create([
        'active' => true,
    ]);
    $this->be($user);

    $response = $this->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});
