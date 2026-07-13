<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

it('renders forgot password page', function (): void {
    $response = $this->fromRoute('login')
        ->get(route('password.request'));

    $response->assertOk()
        ->assertSeeLivewire('pages::auth.forgot-password');
});

it('can request a reset password link', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test('pages::auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('can render the reset password screen', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test('pages::auth.forgot-password')
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

    Livewire::test('pages::auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user): true {
        $response = Livewire::test('pages::auth.reset-password', ['token' => $notification->token])
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

    $response = Livewire::test('pages::auth.reset-password', ['token' => 'invalid-token'])
        ->set('email', $user->email)
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'pass123WORD!@£')
        ->call('resetPassword');

    $response->assertHasErrors(['email']);
});

it('fails with non-existent email', function (): void {
    $response = Livewire::test('pages::auth.reset-password', ['token' => 'some-token'])
        ->set('email', 'nonexistent@example.com')
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'pass123WORD!@£')
        ->call('resetPassword');

    $response->assertHasErrors(['email']);
});

it('requires email', function (): void {
    $response = Livewire::test('pages::auth.reset-password', ['token' => 'some-token'])
        ->set('email', '')
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'pass123WORD!@£')
        ->call('resetPassword');
    $response->assertHasErrors(['email']);
});

it('requires password', function (): void {
    $user = User::factory()->create();

    $response = Livewire::test('pages::auth.reset-password', ['token' => 'some-token'])
        ->set('email', $user->email)
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('resetPassword');
    $response->assertHasErrors(['password']);
});

it('requires password confirmation', function (): void {
    $user = User::factory()->create();

    $response = Livewire::test('pages::auth.reset-password', ['token' => 'some-token'])
        ->set('email', $user->email)
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'different-password')
        ->call('resetPassword');
    $response->assertHasErrors(['password']);
});

it('requires matching password confirmation', function (): void {
    $user = User::factory()->create();

    $response = Livewire::test('pages::auth.reset-password', ['token' => 'some-token'])
        ->set('email', $user->email)
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'different-password')
        ->call('resetPassword');
    $response->assertHasErrors(['password']);
});

it('throttles reset link requests after five attempts', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        Livewire::test('pages::auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors();
    }

    Livewire::test('pages::auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink')
        ->assertHasErrors(['email']);

    Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
});

it('still shows the generic status when the reset mail cannot be sent', function (): void {
    Password::shouldReceive('sendResetLink')
        ->once()
        ->andThrow(new RuntimeException('smtp down'));

    Livewire::test('pages::auth.forgot-password')
        ->set('email', 'someone@example.com')
        ->call('sendPasswordResetLink')
        ->assertHasNoErrors()
        ->assertSee(__('A reset link will be sent if the account exists.'));
});

it('throttles password reset attempts after five tries', function (): void {
    $this->travelTo(now());

    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        Livewire::test('pages::auth.reset-password', ['token' => 'wrong-token'])
            ->set('email', $user->email)
            ->set('password', 'pass123WORD!@£')
            ->set('password_confirmation', 'pass123WORD!@£')
            ->call('resetPassword')
            ->assertHasErrors(['email']);
    }

    Livewire::test('pages::auth.reset-password', ['token' => 'wrong-token'])
        ->set('email', $user->email)
        ->set('password', 'pass123WORD!@£')
        ->set('password_confirmation', 'pass123WORD!@£')
        ->call('resetPassword')
        ->assertHasErrors(['email' => __('auth.throttle', ['seconds' => 300, 'minutes' => 5])]);
});
