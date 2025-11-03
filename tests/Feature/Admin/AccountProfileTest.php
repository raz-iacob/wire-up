<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('can render the account profile screen', function (): void {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('secret'),
        'active' => true,
        'admin' => true,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.account-profile'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.account-profile');
});

it('redirects authenticated non-admin users away from account profile', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.account-profile'));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from account profile', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('admin.account-profile'));

    $response->assertRedirectToRoute('login');
});

it('allows admin users to update their profile information', function (): void {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'admin' => true,
        'active' => true,
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::admin.account-profile')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('update');

    $response->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);
});

it('requires name and email when updating profile information', function (): void {
    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::admin.account-profile')
        ->set('name', '')
        ->set('email', '')
        ->call('update');

    $response->assertHasErrors(['name', 'email']);
});

it('will not resend email verification if email already verified', function (): void {
    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.account-profile')
        ->call('resendVerificationLink')
        ->assertDispatched('toast-show', fn ($_, $payload): bool => ($payload['slots']['text'] ?? null) === __('Your email address is already verified.')
        );
});

it('can resend email verification', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'email_verified_at' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.account-profile')
        ->call('resendVerificationLink')
        ->assertDispatched('toast-show', fn ($_, $payload): bool => ($payload['slots']['text'] ?? null) === __('A new verification link has been sent to your email address.')
        );

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('requires current password to delete account', function (): void {
    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::admin.account-profile')
        ->set('password', 'wrong-password')
        ->call('delete');

    $response->assertHasErrors(['password']);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
    ]);
});

it('allows admin users to delete their account', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('secret'),
        'admin' => true,
        'active' => true,
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::admin.account-profile')
        ->set('password', 'secret')
        ->call('delete');

    $response->assertHasNoErrors();

    expect($user->fresh())->toBeNull();

    $this->assertGuest();
});
