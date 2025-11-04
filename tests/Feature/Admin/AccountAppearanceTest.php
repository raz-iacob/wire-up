<?php

declare(strict_types=1);

use App\Models\User;

it('can render the account appearance screen', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => true,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.account-appearance'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.account-appearance');
});

it('redirects authenticated non-admin users away from account appearance', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.account-appearance'));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from account appearance', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('admin.account-appearance'));

    $response->assertRedirectToRoute('login');
});
