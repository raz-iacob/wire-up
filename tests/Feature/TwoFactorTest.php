<?php

declare(strict_types=1);

use App\Actions\ConfirmTwoFactorAction;
use App\Actions\DisableTwoFactorAction;
use App\Actions\EnableTwoFactorAction;
use App\Actions\RegenerateRecoveryCodesAction;
use App\Actions\VerifyTwoFactorCodeAction;
use App\Models\Settings;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

function secretFor(User $user): string
{
    return (string) Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);
}

function currentCodeFor(User $user): string
{
    return resolve(Google2FA::class)->getCurrentOtp(secretFor($user));
}

function userWithTwoFactor(string $role = 'admin'): User
{
    $user = User::factory()->{$role}()->create(['active' => true]);

    resolve(EnableTwoFactorAction::class)->handle($user);
    $user->refresh();
    resolve(ConfirmTwoFactorAction::class)->handle($user, currentCodeFor($user));

    Cache::flush();

    return $user->refresh();
}

it('turns two-factor authentication on without confirming it yet', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);

    resolve(EnableTwoFactorAction::class)->handle($user);
    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->not->toBeNull()
        ->and($user->recoveryCodes())->toHaveCount(8)
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('leaves an existing secret alone unless forced', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);

    resolve(EnableTwoFactorAction::class)->handle($user);
    $first = secretFor($user->refresh());

    resolve(EnableTwoFactorAction::class)->handle($user);
    expect(secretFor($user->refresh()))->toBe($first);

    resolve(EnableTwoFactorAction::class)->handle($user, force: true);
    expect(secretFor($user->refresh()))->not->toBe($first);
});

it('confirms two-factor authentication with a valid code', function (): void {
    $user = userWithTwoFactor();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->two_factor_confirmed_at)->not->toBeNull();
});

it('rejects a bad confirmation code with a readable message', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);
    resolve(EnableTwoFactorAction::class)->handle($user);
    $user->refresh();

    expect(fn (): mixed => resolve(ConfirmTwoFactorAction::class)->handle($user, '000000'))
        ->toThrow(ValidationException::class);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('turns two-factor authentication off', function (): void {
    $user = userWithTwoFactor();

    resolve(DisableTwoFactorAction::class)->handle($user);

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('does nothing when disabling for a user who never turned it on', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);

    resolve(DisableTwoFactorAction::class)->handle($user);

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

it('regenerates recovery codes', function (): void {
    $user = userWithTwoFactor();
    $before = $user->recoveryCodes();

    resolve(RegenerateRecoveryCodesAction::class)->handle($user);

    expect($user->refresh()->recoveryCodes())->toHaveCount(8)->not->toBe($before);
});

it('verifies a valid timed code', function (): void {
    $user = userWithTwoFactor();

    expect(resolve(VerifyTwoFactorCodeAction::class)->handle($user, currentCodeFor($user)))->toBeTrue();
});

it('refuses to accept the same timed code twice', function (): void {
    $user = userWithTwoFactor();
    $code = currentCodeFor($user);

    expect(resolve(VerifyTwoFactorCodeAction::class)->handle($user, $code))->toBeTrue()
        ->and(resolve(VerifyTwoFactorCodeAction::class)->handle($user, $code))->toBeFalse();
});

it('rejects an invalid timed code', function (): void {
    $user = userWithTwoFactor();

    expect(resolve(VerifyTwoFactorCodeAction::class)->handle($user, '000000'))->toBeFalse();
});

it('rejects an empty submission', function (): void {
    $user = userWithTwoFactor();

    expect(resolve(VerifyTwoFactorCodeAction::class)->handle($user))->toBeFalse();
});

it('rejects a timed code when no secret is stored', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);

    expect(resolve(VerifyTwoFactorCodeAction::class)->handle($user, '000000'))->toBeFalse();
});

it('consumes a recovery code once', function (): void {
    $user = userWithTwoFactor();
    $codes = $user->recoveryCodes();
    $code = $codes[0];

    expect(resolve(VerifyTwoFactorCodeAction::class)->handle($user, recoveryCode: $code))->toBeTrue();

    $user->refresh();

    expect($user->recoveryCodes())->not->toContain($code)->toHaveCount(8)
        ->and(resolve(VerifyTwoFactorCodeAction::class)->handle($user, recoveryCode: $code))->toBeFalse();
});

it('rejects a recovery code when none are stored', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);

    expect(resolve(VerifyTwoFactorCodeAction::class)->handle($user, recoveryCode: 'nope'))->toBeFalse();
});

it('sends a two-factor user to the challenge instead of logging them in', function (): void {
    $user = userWithTwoFactor();

    Livewire::test('pages::auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->set('remember', true)
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    expect(Session::get('login.id'))->toBe($user->id)
        ->and(Session::get('login.remember'))->toBeTrue();

    $this->assertGuest();
});

it('logs a user without two-factor straight in', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);

    Livewire::test('pages::auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('completes the challenge with a timed code', function (): void {
    $user = userWithTwoFactor();
    Session::put(['login.id' => $user->id, 'login.remember' => false]);

    Livewire::test('pages::auth.two-factor-challenge')
        ->set('code', currentCodeFor($user))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(Session::has('login.id'))->toBeFalse();
});

it('completes the challenge with a recovery code', function (): void {
    $user = userWithTwoFactor();
    Session::put(['login.id' => $user->id]);

    Livewire::test('pages::auth.two-factor-challenge')
        ->call('useRecoveryCode')
        ->set('recovery_code', $user->recoveryCodes()[0])
        ->call('verify')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

it('sends a member home after the challenge', function (): void {
    $user = userWithTwoFactor('member');
    Session::put(['login.id' => $user->id]);

    Livewire::test('pages::auth.two-factor-challenge')
        ->set('code', currentCodeFor($user))
        ->call('verify')
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('keeps a wrong challenge code out', function (): void {
    $user = userWithTwoFactor();
    Session::put(['login.id' => $user->id]);

    Livewire::test('pages::auth.two-factor-challenge')
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors('code');

    $this->assertGuest();
});

it('reports a bad recovery code against the recovery field', function (): void {
    $user = userWithTwoFactor();
    Session::put(['login.id' => $user->id]);

    Livewire::test('pages::auth.two-factor-challenge')
        ->call('useRecoveryCode')
        ->set('recovery_code', 'not-a-code')
        ->call('verify')
        ->assertHasErrors('recovery_code');

    $this->assertGuest();
});

it('sends someone with no pending challenge back to the login page', function (): void {
    Livewire::test('pages::auth.two-factor-challenge')->assertRedirect(route('login'));
});

it('switches back to the authenticator code input', function (): void {
    $user = userWithTwoFactor();
    Session::put(['login.id' => $user->id]);

    Livewire::test('pages::auth.two-factor-challenge')
        ->call('useRecoveryCode')
        ->assertSet('usingRecoveryCode', true)
        ->call('useAuthenticatorCode')
        ->assertSet('usingRecoveryCode', false);
});

it('manages two-factor from the account screen', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $this->actingAs($user);

    $component = Livewire::test('shared.two-factor')
        ->assertSet('showingRecoveryCodes', false)
        ->call('enable');

    $user->refresh();

    $component->set('code', currentCodeFor($user))
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertSet('showingRecoveryCodes', true);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $component->call('regenerateRecoveryCodes')->assertSet('showingRecoveryCodes', true);

    $component->set('disable_password', 'password')
        ->call('disable')
        ->assertHasNoErrors();

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('will not turn two-factor off without the right password', function (): void {
    $user = userWithTwoFactor('member');
    $this->actingAs($user);

    Livewire::test('shared.two-factor')
        ->set('disable_password', 'wrong-password')
        ->call('disable')
        ->assertHasErrors('disable_password');

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('abandons an unconfirmed setup', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $this->actingAs($user);

    Livewire::test('shared.two-factor')
        ->call('enable')
        ->call('cancelSetup');

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

it('reveals recovery codes on request', function (): void {
    $user = userWithTwoFactor('member');
    $this->actingAs($user);

    Livewire::test('shared.two-factor')
        ->assertSet('showingRecoveryCodes', false)
        ->call('showRecoveryCodes')
        ->assertSet('showingRecoveryCodes', true)
        ->assertSee($user->recoveryCodes()[0]);
});

it('shows the admin security screen', function (): void {
    $this->actingAs(User::factory()->admin()->create(['active' => true]));

    $this->get(route('admin.account-security'))
        ->assertOk()
        ->assertSee(__('Two-factor authentication'));
});

it('leaves staff alone when two-factor is not required', function (): void {
    $this->actingAs(User::factory()->admin()->create(['active' => true]));

    expect(SettingsService::current()->requiresTwoFactor())->toBeFalse();

    $this->get(route('admin.dashboard'))->assertOk();
});

it('pushes staff without two-factor to the security screen when it is required', function (): void {
    Settings::set(['require_two_factor' => true]);
    $this->actingAs(User::factory()->admin()->create(['active' => true]));

    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.account-security'));
    $this->get(route('admin.account-security'))->assertOk();
});

it('lets staff with two-factor through when it is required', function (): void {
    Settings::set(['require_two_factor' => true]);
    $this->actingAs(userWithTwoFactor());

    $this->get(route('admin.dashboard'))->assertOk();
});

it('never blocks a member from the site when two-factor is required', function (): void {
    Settings::set(['require_two_factor' => true]);
    $this->actingAs(User::factory()->member()->create(['active' => true]));

    $this->get(route('account'))->assertOk();
});

it('saves the staff two-factor requirement from general settings', function (): void {
    $this->actingAs(User::factory()->owner()->create(['active' => true]));

    Livewire::test('pages::admin.settings-general')
        ->assertSet('require_two_factor', false)
        ->set('require_two_factor', true)
        ->call('update')
        ->assertHasNoErrors();

    expect(SettingsService::current()->requiresTwoFactor())->toBeTrue();
});
