<?php

declare(strict_types=1);

use App\Models\User;

it('logs out authenticated user', function (): void {

    $user = User::factory()->create([
        'active' => true,
    ]);
    $this->be($user);

    $response = $this->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
