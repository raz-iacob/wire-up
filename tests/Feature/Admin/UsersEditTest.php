<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('can render the users edit screen', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $response = $this->actingAs($admin)
        ->fromRoute('admin.users-index')
        ->get(route('admin.users-edit', $user));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.users-edit');
});

it('redirects authenticated non-admin users away from users edit', function (): void {
    $nonAdmin = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.users-edit', $user));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from users edit', function (): void {
    $user = User::factory()->create();

    $response = $this->fromRoute('home')
        ->get(route('admin.users-edit', $user));

    $response->assertRedirectToRoute('login');
});

it('populates form with user data on mount', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'active' => false,
    ]);

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user]);

    $response->assertSet('name', 'Jane Smith')
        ->assertSet('email', 'jane@example.com')
        ->assertSet('active', false)
        ->assertSet('user.id', $user->id);
});

it('displays user creation and last login information', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Test User',
        'last_seen_at' => now()->subDays(3),
        'created_at' => now()->subWeeks(2),
    ]);

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user]);

    $response->assertSee($user->created_at->format('M d, Y H:i'))
        ->assertSee($user->last_seen_at->format('M d, Y H:i'));
});

it('shows never for users who have not logged in', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'New User',
        'last_seen_at' => null,
    ]);

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user]);

    $response->assertSee('Never');
});

it('can update user basic information', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'active' => false,
    ]);

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->set('active', true)
        ->call('update');

    $response->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'New Name',
        'email' => 'new@example.com',
        'active' => true,
    ]);
});

it('resets email verification when email changes', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create([
        'email' => 'verified@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('email', 'newemail@example.com')
        ->call('update');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'newemail@example.com',
        'email_verified_at' => null,
    ]);
});

it('keeps email verification when email stays the same', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $verificationTime = now();
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'email_verified_at' => $verificationTime,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('name', 'John Smith')
        ->call('update');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'John Smith',
        'email' => 'john@example.com',
        'email_verified_at' => $verificationTime,
    ]);
});

it('validates required fields', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('name', '')
        ->set('email', '')
        ->call('update');

    $response->assertHasErrors(['name', 'email']);
});

it('validates email format', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('name', 'Valid Name')
        ->set('email', 'invalid-email')
        ->call('update');

    $response->assertHasErrors(['email']);
});

it('validates email uniqueness except for current user', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $userToEdit = User::factory()->create([
        'email' => 'current@example.com',
    ]);

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $userToEdit])
        ->set('email', 'existing@example.com')
        ->call('update');

    $response->assertHasErrors(['email']);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $userToEdit])
        ->set('email', 'current@example.com')
        ->call('update');

    $response->assertHasNoErrors();
});

it('validates name length', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('name', str_repeat('a', 256))
        ->set('email', 'valid@example.com')
        ->call('update');

    $response->assertHasErrors(['name']);
});

it('validates email length', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($admin);

    $longEmail = str_repeat('a', 250).'@example.com';

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('name', 'Valid Name')
        ->set('email', $longEmail)
        ->call('update');

    $response->assertHasErrors(['email']);
});

it('can update user password', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('password', 'newpassword123')
        ->set('password_confirmation', 'newpassword123')
        ->call('update');

    $response->assertHasNoErrors();

    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

it('validates password confirmation', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('password', 'newpassword123')
        ->set('password_confirmation', 'differentpassword')
        ->call('update');

    $response->assertHasErrors(['password']);
});

it('validates password strength', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('password', '123')
        ->set('password_confirmation', '123')
        ->call('update');

    $response->assertHasErrors(['password']);
});

it('does not require password to update other fields', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Old Name',
        'password' => Hash::make('originalpassword'),
    ]);

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('name', 'New Name')
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('update');

    $response->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and(Hash::check('originalpassword', $user->password))->toBeTrue();
});

it('updates basic info and password together', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'active' => false,
    ]);

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->set('active', true)
        ->set('password', 'newpassword123')
        ->set('password_confirmation', 'newpassword123')
        ->call('update');

    $response->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->email)->toBe('new@example.com')
        ->and($user->active)->toBeTrue()
        ->and(Hash::check('newpassword123', $user->password))->toBeTrue();
});

it('shows success message after update', function (): void {
    $admin = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($admin);

    $response = Livewire::test('pages::admin.users-edit', ['user' => $user])
        ->set('name', 'Updated Name')
        ->call('update');

    $response->assertDispatched('toast-show', fn ($_, $payload): bool => str_contains($payload['slots']['text'] ?? '', __('User details have been updated')));
});
