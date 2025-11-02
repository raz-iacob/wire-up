<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

it('can render the login screen', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('login'));

    $response->assertOk()
        ->assertSeeLivewire('pages::auth.login');
});

it('can authenticate users using the login screen', function (): void {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('secret'),
        'active' => true,
    ]);
    $component = Livewire::test('pages::auth.login');

    $component->set('email', $user->email)
        ->set('password', 'secret')
        ->call('login');

    $component->assertHasNoErrors()
        ->assertRedirect(route('home'));
});

it('can not authenticate with invalid password', function (): void {
    $user = User::factory()->create();

    $response = Livewire::test('pages::auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-pass123WORD!@£')
        ->call('login');

    $response->assertHasErrors('email');

    $this->assertGuest();
});

it('rejects login if user is inactive', function (): void {

    $user = User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => bcrypt('secret'),
        'admin' => true,
        'active' => false,
    ]);

    $component = Livewire::test('pages::auth.login');

    $component->set('email', $user->email)
        ->set('password', 'secret')
        ->call('login');

    $component->assertHasErrors(['email']);
    $this->assertGuest();
});

it('allows login attempts when under rate limit threshold', function (): void {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('secret'),
        'active' => true,
    ]);

    $component = Livewire::test('pages::auth.login');

    for ($i = 0; $i < 4; $i++) {
        $component->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login');
    }

    $component->set('email', $user->email)
        ->set('password', 'secret')
        ->call('login');

    $component->assertHasNoErrors()
        ->assertRedirect(route('home'));
});

it('blocks login attempts when rate limit is exceeded', function (): void {
    Event::fake();

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('secret'),
        'active' => true,
    ]);

    $component = Livewire::test('pages::auth.login');

    // Manually hit the rate limiter 5 times to simulate failed attempts
    $throttleKey = mb_strtolower($user->email).'|127.0.0.1';
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($throttleKey);
    }

    // Now the login attempt should be rate limited
    $component->set('email', $user->email)
        ->set('password', 'secret')
        ->call('login');

    $component->assertHasErrors(['email']);
    $this->assertGuest();

    Event::assertDispatched(Lockout::class);
});

it('clears rate limiter on successful authentication', function (): void {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('secret'),
        'active' => true,
    ]);

    $throttleKey = mb_strtolower($user->email).'|127.0.0.1';
    for ($i = 0; $i < 4; $i++) {
        RateLimiter::hit($throttleKey);
    }

    expect(RateLimiter::attempts($throttleKey))->toBe(4);

    $component = Livewire::test('pages::auth.login');

    $component->set('email', $user->email)
        ->set('password', 'secret')
        ->call('login');

    $component->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticated();

    expect(RateLimiter::attempts($throttleKey))->toBe(0);
});

it('uses correct throttle key based on email and IP', function (): void {
    $user1 = User::factory()->create([
        'email' => 'user1@example.com',
        'password' => bcrypt('secret'),
        'active' => true,
    ]);

    $user2 = User::factory()->create([
        'email' => 'user2@example.com',
        'password' => bcrypt('secret'),
        'active' => true,
    ]);

    $throttleKey1 = mb_strtolower($user1->email).'|127.0.0.1';
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($throttleKey1);
    }

    $component1 = Livewire::test('pages::auth.login');
    $component1->set('email', $user1->email)
        ->set('password', 'secret')
        ->call('login');

    $component1->assertHasErrors(['email']);

    $component2 = Livewire::test('pages::auth.login');
    $component2->set('email', $user2->email)
        ->set('password', 'secret')
        ->call('login');

    $component2->assertHasNoErrors()
        ->assertRedirect(route('home'));
});

it('redirects authenticated non-admin users away from login', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('login'));

    $response->assertRedirectToRoute('home');
});

it('redirects authenticated admin users away from login', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => true,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('login'));

    $response->assertRedirectToRoute('admin.dashboard');
});
