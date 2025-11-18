<?php

declare(strict_types=1);

use App\Models\User;

it('can render the dashboard screen', function (): void {
    $response = $this->actingAsAdmin()
        ->fromRoute('home')
        ->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.dashboard');
});

it('redirects authenticated non-admin users away from dashboard', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.dashboard'));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from dashboard', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('admin.dashboard'));

    $response->assertRedirectToRoute('login');
});
