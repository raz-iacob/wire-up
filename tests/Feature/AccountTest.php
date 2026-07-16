<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $overrides
 */
function accountMenuConfig(array $overrides = []): void
{
    config()->set('site.menus', [[
        'key' => 'header',
        'name' => 'Header',
        'display' => ['background' => true, 'position' => 'right', 'sticky' => false, 'mobile' => 'collapse'],
        'items' => ['en' => [[
            'type' => 'account',
            'appearance' => 'link',
            'target' => '_self',
            'label' => '',
            'page_id' => null,
            'url' => '',
            'icon' => '',
            'icon_svg' => '',
            'badge' => '',
            'badgeColor' => 'zinc',
            ...$overrides,
        ]]],
    ]]);
}

it('redirects guests away from the account page', function (): void {
    $this->get(route('account'))->assertRedirect(route('login'));
});

it('shows the account page to a signed-in member', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('account'))
        ->assertOk()
        ->assertSeeLivewire('pages::account');
});

it('updates the member name and email', function (): void {
    $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

    Livewire::actingAs($user)->test('pages::account')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('updateProfile')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('New Name')
        ->and($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

it('keeps verification when the email is unchanged', function (): void {
    $user = User::factory()->create(['email' => 'same@example.com', 'email_verified_at' => now()]);

    Livewire::actingAs($user)->test('pages::account')
        ->set('name', 'Renamed')
        ->call('updateProfile')
        ->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('rejects a duplicate email', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::account')
        ->set('email', 'taken@example.com')
        ->call('updateProfile')
        ->assertHasErrors('email');
});

it('changes the member password', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::account')
        ->set('current_password', 'password')
        ->set('password', 'new-password-1234')
        ->set('password_confirmation', 'new-password-1234')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-1234', $user->refresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::account')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password-1234')
        ->set('password_confirmation', 'new-password-1234')
        ->call('updatePassword')
        ->assertHasErrors('current_password');
});

it('resends the verification email when unverified', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email_verified_at' => null]);

    Livewire::actingAs($user)->test('pages::account')->call('resendVerification');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not resend when already verified', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)->test('pages::account')->call('resendVerification');

    Notification::assertNothingSent();
});

it('logs the member out and sends them home', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::account')
        ->call('logout')
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

it('deletes the account with the correct password', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::account')
        ->set('delete_password', 'password')
        ->call('delete')
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('refuses to delete with a wrong password', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::account')
        ->set('delete_password', 'nope')
        ->call('delete')
        ->assertHasErrors('delete_password');

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('expands an account menu item to login and signup for guests', function (): void {
    config()->set('site.allow_registration', true);
    accountMenuConfig();

    $items = SettingsService::current()->menu('header');

    expect(collect($items)->pluck('label')->all())->toBe([__('Log in'), __('Sign up')])
        ->and($items[0]['url'])->toBe(route('login'))
        ->and($items[1]['url'])->toBe(route('register'));
});

it('omits signup when registration is closed', function (): void {
    config()->set('site.allow_registration', false);
    accountMenuConfig();

    $items = SettingsService::current()->menu('header');

    expect(collect($items)->pluck('label')->all())->toBe([__('Log in')]);
});

it('expands an account menu item to the account link for signed-in members', function (): void {
    accountMenuConfig(['appearance' => 'button']);
    $this->actingAs(User::factory()->create());

    $items = SettingsService::current()->menu('header');

    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toBe(__('Account'))
        ->and($items[0]['url'])->toBe(route('account'))
        ->and($items[0]['appearance'])->toBe('button');
});
