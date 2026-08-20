<?php

declare(strict_types=1);

use App\Actions\ConfirmTwoFactorAction;
use App\Actions\EnableTwoFactorAction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

function browserUserWithTwoFactor(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'tfa@example.com',
        'active' => true,
    ]);

    resolve(EnableTwoFactorAction::class)->handle($user);
    $user->refresh();

    $secret = (string) Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

    resolve(ConfirmTwoFactorAction::class)->handle($user, resolve(Google2FA::class)->getCurrentOtp($secret));

    Cache::flush();

    return $user->refresh();
}

it('challenges a two-factor user at login and signs them in with a code', function (): void {
    $user = browserUserWithTwoFactor();
    $secret = (string) Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

    $page = visit(route('login'));

    $page->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs('/two-factor-challenge')
        ->assertSee('Enter your authentication code')
        ->assertNoJavascriptErrors();

    $code = resolve(Google2FA::class)->getCurrentOtp($secret);

    $page->click('[data-flux-otp] input[tabindex="0"]')
        ->type('[data-flux-otp] input[tabindex="0"]', $code);

    $page->assertScript(
        "window.Livewire.all().find(c => c.\$wire.get('code') !== undefined).\$wire.get('code')",
        $code,
    );

    $page->press('Continue')
        ->assertPathIs('/admin')
        ->assertNoJavascriptErrors();
});

it('lets a two-factor user in with a recovery code', function (): void {
    $user = browserUserWithTwoFactor();

    $page = visit(route('login'));

    $page->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs('/two-factor-challenge');

    $page->click('Use a recovery code instead')
        ->assertSee('Enter a recovery code')
        ->type('recovery_code', $user->recoveryCodes()[0])
        ->press('Continue')
        ->assertPathIs('/admin')
        ->assertNoJavascriptErrors();
});

it('walks an admin through turning two-factor on', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);
    $this->actingAs($user);

    $page = visit(route('admin.account-security'));

    $page->assertSee('Two-factor authentication')
        ->assertNoJavascriptErrors()
        ->press('Turn on two-factor authentication')
        ->assertSee('Scan this with your authenticator app')
        ->assertPresent('[data-two-factor-qr]')
        ->assertNoJavascriptErrors();

    $user->refresh();

    $secret = (string) Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

    $page->type('code', resolve(Google2FA::class)->getCurrentOtp($secret))
        ->press('Confirm')
        ->assertSee('Recovery codes')
        ->assertNoJavascriptErrors();

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});
