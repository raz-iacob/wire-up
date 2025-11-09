<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

it('can upload a profile photo', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'name' => 'John Doe',
        'photo' => null,
    ]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->image('profile.jpg', 300, 300);

    Livewire::test('pages::admin.account-profile')
        ->set('photo', $file)
        ->call('update')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    $user->refresh();

    expect($user->photo)
        ->not->toBeNull()
        ->toStartWith('users/'.$user->id.'_profile');

    Storage::disk('public')->assertExists($user->photo);
});

it('can replace an existing profile photo', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'photo' => 'users/1_old-photo.jpg',
    ]);

    Storage::disk('public')->put('users/1_old-photo.jpg', 'old content');

    $this->actingAs($user);

    $newFile = UploadedFile::fake()->image('new-profile.png', 300, 300);

    Livewire::test('pages::admin.account-profile')
        ->set('photo', $newFile)
        ->call('update')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    $user->refresh();

    Storage::disk('public')->assertMissing('users/1_old-photo.jpg');

    expect($user->photo)
        ->toStartWith('users/'.$user->id.'_new-profile')
        ->toEndWith('.png');

    Storage::disk('public')->assertExists($user->photo);
});

it('can delete current profile photo', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'photo' => null,
    ]);

    $photoPath = "users/{$user->id}_photo.jpg";
    $user->update(['photo' => $photoPath]);

    Storage::disk('public')->put($photoPath, 'photo content');

    $this->actingAs($user);

    $component = Livewire::test('pages::admin.account-profile');

    expect($component->get('user')->photo)->toBe($photoPath);

    $component->call('removePhoto');

    $component->call('update')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    $user->refresh();

    expect($user->photo)->toBeNull();

    expect(Storage::disk('public')->exists($photoPath))->toBeFalse();
});

it('validates photo file size', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $this->actingAs($user);

    $largeFile = UploadedFile::fake()->image('large.jpg')->size(11264);

    $component = Livewire::test('pages::admin.account-profile')
        ->set('photo', $largeFile)
        ->assertHasErrors(['photo']);

    expect($component->get('photo'))->toBeNull();
});

it('validates photo mime types', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
    ]);

    $this->actingAs($user);

    $webpFile = UploadedFile::fake()->create('image.pdf', 1024, 'image/pdf');

    $component = Livewire::test('pages::admin.account-profile')
        ->set('photo', $webpFile)
        ->assertHasErrors(['photo']);

    expect($component->get('photo'))->toBeNull();
});

it('can remove temporary uploaded photo before saving', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'photo' => null,
    ]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->image('temp.jpg');

    $component = Livewire::test('pages::admin.account-profile')
        ->set('photo', $file);

    expect($component->get('photo'))->not->toBeNull();

    $component->call('removePhoto');

    expect($component->get('photo'))->toBeNull();

    $user->refresh();
    expect($user->photo)->toBeNull();
});

it('can update profile with name and email while also uploading photo', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'photo' => null,
    ]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->image('combined.jpg');

    Livewire::test('pages::admin.account-profile')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->set('photo', $file)
        ->call('update')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    $user->refresh();

    expect($user->name)->toBe('New Name')
        ->and($user->email)->toBe('new@example.com')
        ->and($user->photo)->not->toBeNull();

    Storage::disk('public')->assertExists($user->photo);
});

it('does not affect existing photo when updating profile without new photo', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'name' => 'Old Name',
        'photo' => 'users/1_existing.jpg',
    ]);

    Storage::disk('public')->put('users/1_existing.jpg', 'existing content');

    $this->actingAs($user);

    Livewire::test('pages::admin.account-profile')
        ->set('name', 'Updated Name')
        ->call('update')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->photo)->toBe('users/1_existing.jpg');

    Storage::disk('public')->assertExists('users/1_existing.jpg');
});

it('handles delete current photo when no photo exists', function (): void {
    $user = User::factory()->create([
        'admin' => true,
        'active' => true,
        'photo' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.account-profile')
        ->call('removePhoto')
        ->call('update')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->photo)->toBeNull();
});
