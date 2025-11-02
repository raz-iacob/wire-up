<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('renders registration page', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('register'));

    $response->assertOk()
        ->assertSeeLivewire('pages::auth.register');
});

it('may register a new user', function (): void {
    Event::fake([Registered::class]);

    $component = Livewire::test('pages::auth.register');

    $component->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password1234')
        ->set('password_confirmation', 'password1234')
        ->call('register');

    $component->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::query()->where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and(Hash::check('password1234', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);

    Event::assertDispatched(Registered::class);
});

it('requires name', function (): void {
    $component = Livewire::test('pages::auth.register');

    $component->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $component->assertHasErrors('name');
});

it('requires email', function (): void {
    $component = Livewire::test('pages::auth.register');

    $component->set('name', 'Test User')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $component->assertHasErrors('email');
});

it('requires valid email', function (): void {
    $component = Livewire::test('pages::auth.register');

    $component->set('name', 'Test User')
        ->set('email', 'not-an-email')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $component->assertHasErrors('email');
});

it('requires unique email', function (): void {
    User::factory()->create(['email' => 'test@example.com']);

    $component = Livewire::test('pages::auth.register');

    $component->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $component->assertHasErrors('email');
});

it('requires password', function (): void {
    $component = Livewire::test('pages::auth.register');

    $component->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('register');

    $component->assertHasErrors('password');
});

it('requires password confirmation', function (): void {
    $component = Livewire::test('pages::auth.register');

    $component->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->call('register');

    $component->assertHasErrors('password');
});

it('requires matching password confirmation', function (): void {
    $component = Livewire::test('pages::auth.register');

    $component->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'different-password')
        ->call('register');

    $component->assertHasErrors('password');
});

it('redirects authenticated non-admin users away from registration', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('register'));

    $response->assertRedirectToRoute('home');
});

it('redirects authenticated admin users away from registration', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => true,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('register'));

    $response->assertRedirectToRoute('admin.dashboard');
});
