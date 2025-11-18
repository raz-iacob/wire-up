<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\AdminInvite;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('can render the users index screen', function (): void {
    $response = $this->actingAsAdmin()
        ->fromRoute('admin.dashboard')
        ->get(route('admin.users-index'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.users-index');
});

it('redirects authenticated non-admin users away from users index', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.users-index'));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from users index', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('admin.users-index'));

    $response->assertRedirectToRoute('login');
});

it('displays users in the table', function (): void {
    $users = User::factory()->count(3)->create([
        'admin' => false,
        'active' => true,
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index');

    foreach ($users as $user) {
        $response->assertSee($user->name)
            ->assertSee($user->email);
    }
});

it('can search users by name', function (): void {
    User::factory()->createMany([
        ['name' => 'John Doe', 'email' => 'john@example.com'],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index')
        ->set('search', 'John')
        ->call('$refresh');

    $response->assertSee('John Doe')
        ->assertDontSee('Jane Smith');
});

it('can search users by email', function (): void {
    User::factory()->createMany([
        ['name' => 'John Doe', 'email' => 'john@example.com'],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index')
        ->set('search', 'jane@example.com')
        ->call('$refresh');

    $response->assertSee('Jane Smith')
        ->assertDontSee('John Doe');
});

it('can filter users by status', function (): void {
    User::factory()->createMany([
        ['name' => 'Active User', 'active' => true],
        ['name' => 'Inactive User', 'active' => false],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index')
        ->set('status', 'active')
        ->call('$refresh');

    $response->assertSee('Active User')
        ->assertDontSee('Inactive User');

    $response = Livewire::test('pages::admin.users-index')
        ->set('status', 'disabled')
        ->call('$refresh');

    $response->assertSee('Inactive User')
        ->assertDontSee('Active User');
});

it('can sort users by name', function (): void {
    User::factory()->createMany([
        ['name' => 'Zebra User'],
        ['name' => 'Alpha User'],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index')
        ->call('sort', 'name');

    $response->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['Alpha User', 'Zebra User']);

    $response->call('sort', 'name');

    $response->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Zebra User', 'Alpha User']);
});

it('can sort users by email', function (): void {
    User::factory()->createMany([
        ['email' => 'zebra@example.com'],
        ['email' => 'alpha@example.com'],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index')
        ->call('sort', 'email');

    $response->assertSet('sortBy', 'email')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['alpha@example.com', 'zebra@example.com']);

    $response->call('sort', 'email');

    $response->assertSet('sortBy', 'email')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['zebra@example.com', 'alpha@example.com']);
});

it('can invite a new user', function (): void {
    Notification::fake();

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index')
        ->set('name', 'New User')
        ->set('email', 'newuser@example.com')
        ->call('create');

    $response->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'admin' => true,
    ]);

    $newUser = User::query()->where('email', 'newuser@example.com')->first();
    Notification::assertSentTo($newUser, AdminInvite::class);

    $response->assertDispatched(event: 'modal-close', name: 'add-new')
        ->assertDispatched('toast-show', fn ($_, $payload): bool => str_contains($payload['slots']['text'] ?? '', __('Invitation email sent to ').'newuser@example.com'));
});

it('validates name and email when inviting new user', function (): void {
    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index')
        ->set('name', '')
        ->set('email', '')
        ->call('create');

    $response->assertHasErrors(['name', 'email']);

    $response = Livewire::test('pages::admin.users-index')
        ->set('name', 'Valid Name')
        ->set('email', 'invalid-email')
        ->call('create');

    $response->assertHasErrors(['email']);

    $response = Livewire::test('pages::admin.users-index')
        ->set('name', str_repeat('a', 256))
        ->set('email', 'valid@example.com')
        ->call('create');

    $response->assertHasErrors(['name']);
});

it('validates email uniqueness when inviting new user', function (): void {
    User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index')
        ->set('name', 'New User')
        ->set('email', 'existing@example.com')
        ->call('create');

    $response->assertHasErrors(['email']);
});

it('can toggle user status', function (): void {
    $user = User::factory()->create([
        'name' => 'Test User',
        'active' => true,
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.users-index')
        ->call('toggleStatus', $user->id);

    expect($user->fresh()->active)->toBeFalse();

    Livewire::test('pages::admin.users-index')
        ->call('toggleStatus', $user->id);

    expect($user->fresh()->active)->toBeTrue();
});

it('displays user status badges correctly', function (): void {
    User::factory()->createMany([
        ['name' => 'Active User', 'active' => true],
        ['name' => 'Inactive User', 'active' => false],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index');

    $response->assertSee('Active')
        ->assertSee('Disabled');
});

it('shows last login information', function (): void {
    User::factory()->createMany([
        ['name' => 'User With Login', 'last_seen_at' => now()->subDays(2)],
        ['name' => 'User Without Login', 'last_seen_at' => null],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.users-index');

    $response->assertSee(User::query()->where('name', 'User With Login')->first()->last_seen_at->format('M d, Y H:i'))
        ->assertSee('Never');
});
