<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('can render the account password screen', function (): void {
    $response = $this->actingAsAdmin()
        ->fromRoute('home')
        ->get(route('admin.account-password'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.account-password');
});

it('redirects authenticated non-admin users away from account password', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.account-password'));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from account password', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('admin.account-password'));

    $response->assertRedirectToRoute('login');
});

it('allows admin users to update their password', function (): void {
    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'password' => Hash::make('old-password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::admin.account-password')
        ->set('current_password', 'old-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('update');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

it('requires current password to update password', function (): void {
    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::admin.account-password')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('update');

    $response->assertHasErrors(['current_password']);
    expect($user->fresh()->password)->toBe($user->password);
});

it('requires matching password confirmation to update password', function (): void {
    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::admin.account-password')
        ->set('current_password', 'old-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'different-password')
        ->call('update');

    $response->assertHasErrors(['password']);
    expect($user->fresh()->password)->toBe($user->password);
});
