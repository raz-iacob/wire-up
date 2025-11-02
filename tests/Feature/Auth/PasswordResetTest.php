<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('renders forgot password page', function (): void {
    $response = $this->get('/forgot-password');
    $response->assertStatus(200);
});

it('can request a reset password link', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test('pages::forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('can render the reset password screen', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test('pages::forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification): true {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

it('can reset the password with valid token', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test('pages::forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user): true {
        $response = Livewire::test('pages::reset-password', ['token' => $notification->token])
            ->set('email', $user->email)
            ->set('password', 'pass123WORD!@£')
            ->set('password_confirmation', 'pass123WORD!@£')
            ->call('resetPassword');

        $response
            ->assertHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});

it('fails with invalid token', function (): void {
    $user = User::factory()->create();

    $response = Livewire::test('pages::reset-password', ['token' => 'invalid-token'])
        ->set('email', $user->email)
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'pass123WORD!@£')
        ->call('resetPassword');

    $response->assertHasErrors(['email']);
});

it('fails with non-existent email', function (): void {
    $response = Livewire::test('pages::reset-password', ['token' => 'some-token'])
        ->set('email', 'nonexistent@example.com')
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'pass123WORD!@£')
        ->call('resetPassword');

    $response->assertHasErrors(['email']);
});

it('requires email', function (): void {
    $response = Livewire::test('pages::reset-password', ['token' => 'some-token'])
        ->set('email', '')
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'pass123WORD!@£')
        ->call('resetPassword');
    $response->assertHasErrors(['email']);
});

it('requires password', function (): void {
    $user = User::factory()->create();

    $response = Livewire::test('pages::reset-password', ['token' => 'some-token'])
        ->set('email', $user->email)
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('resetPassword');
    $response->assertHasErrors(['password']);
});

it('requires password confirmation', function (): void {
    $user = User::factory()->create();

    $response = Livewire::test('pages::reset-password', ['token' => 'some-token'])
        ->set('email', $user->email)
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'different-password')
        ->call('resetPassword');
    $response->assertHasErrors(['password']);
});

it('requires matching password confirmation', function (): void {
    $user = User::factory()->create();

    $response = Livewire::test('pages::reset-password', ['token' => 'some-token'])
        ->set('email', $user->email)
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'different-password')
        ->call('resetPassword');
    $response->assertHasErrors(['password']);
});
