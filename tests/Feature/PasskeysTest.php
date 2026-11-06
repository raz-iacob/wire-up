<?php

declare(strict_types=1);

use App\Actions\AddPasskeyAction;
use App\Actions\SignInWithPasskeyAction;
use App\Actions\StartPasskeyRegistrationAction;
use App\Enums\ActivityEvent;
use App\Models\Activity;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkey;
use Livewire\Livewire;
use Tests\Support\FakeAuthenticator;

function passkeyFor(User $user, FakeAuthenticator $authenticator = new FakeAuthenticator, string $name = 'Work laptop'): Passkey
{
    $options = resolve(StartPasskeyRegistrationAction::class)->handle($user);

    return resolve(AddPasskeyAction::class)->handle($user, $name, $authenticator->register($options));
}

/**
 * @return array<array-key, mixed>
 */
function passkeySignInOptions(): array
{
    $options = [];

    Livewire::test('pages::auth.login')
        ->call('passkeyOptions')
        ->assertReturned(function (array $returned) use (&$options): bool {
            $options = $returned;

            return true;
        });

    return $options;
}

it('adds a passkey from the account screen once the password checks out', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $this->actingAs($user);

    $options = [];

    $component = Livewire::test('shared.passkeys')
        ->set('name', ' Work laptop ')
        ->set('password', 'password')
        ->call('registrationOptions')
        ->assertHasNoErrors()
        ->assertReturned(function (array $returned) use (&$options): bool {
            $options = $returned;

            return true;
        });

    $component->call('add', (new FakeAuthenticator)->register($options))
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSet('password', '')
        ->assertSee('Work laptop')
        ->assertSee(__('Never used'));

    expect($user->passkeys()->sole()->name)->toBe('Work laptop')
        ->and(Activity::query()->sole()->only(['event', 'subject_label', 'user_id']))
        ->toBe(['event' => ActivityEvent::PASSKEY_ADDED, 'subject_label' => 'Work laptop', 'user_id' => $user->id]);
});

it('will not start adding a passkey without the right password', function (): void {
    $this->actingAs(User::factory()->member()->create(['active' => true]));

    Livewire::test('shared.passkeys')
        ->set('password', 'wrong-password')
        ->call('registrationOptions')
        ->assertHasErrors('password');

    expect(Session::has(StartPasskeyRegistrationAction::SESSION_KEY))->toBeFalse();
});

it('refuses a passkey that was never asked for', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $this->actingAs($user);

    $credential = (new FakeAuthenticator)->register(resolve(StartPasskeyRegistrationAction::class)->handle($user));
    Session::forget(StartPasskeyRegistrationAction::SESSION_KEY);

    Livewire::test('shared.passkeys')
        ->call('add', $credential)
        ->assertHasErrors('credential');

    expect($user->passkeys()->count())->toBe(0);
});

it('refuses a passkey made for another site', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $options = resolve(StartPasskeyRegistrationAction::class)->handle($user);

    expect(fn (): Passkey => resolve(AddPasskeyAction::class)->handle($user, 'Phone', (new FakeAuthenticator)->register($options, 'https://elsewhere.example')))
        ->toThrow(ValidationException::class, __("That passkey didn't work. Please try again."))
        ->and($user->passkeys()->count())->toBe(0);
});

it('refuses a garbled passkey', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    resolve(StartPasskeyRegistrationAction::class)->handle($user);

    expect(fn (): Passkey => resolve(AddPasskeyAction::class)->handle($user, 'Phone', ['id' => 'nope']))
        ->toThrow(ValidationException::class, __("That passkey didn't work. Please try again."));
});

it('names an unnamed passkey after its password manager', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $googlePasswordManager = (string) hex2bin('ea9b8d664d011d213ce4b6b48cb575d4');

    expect(passkeyFor($user, new FakeAuthenticator($googlePasswordManager), '')->name)->toBe('Google Password Manager')
        ->and(passkeyFor($user, new FakeAuthenticator, '')->name)->toBe('Passkey');
});

it('signs an admin in with a passkey, without asking for a two-factor code', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);
    $authenticator = new FakeAuthenticator;
    passkeyFor($user, $authenticator);
    $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

    $credential = $authenticator->assert(passkeySignInOptions());

    Livewire::test('pages::auth.login')
        ->set('remember', true)
        ->call('signInWithPasskey', $credential)
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);

    expect(Session::get(SignInWithPasskeyAction::SESSION_KEY))->toBeTrue()
        ->and(Session::has('login.id'))->toBeFalse()
        ->and($user->passkeys()->sole()->last_used_at)->not->toBeNull();
});

it('sends a member home after a passkey sign-in', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $authenticator = new FakeAuthenticator;
    passkeyFor($user, $authenticator);

    Livewire::test('pages::auth.login')
        ->call('signInWithPasskey', $authenticator->assert(passkeySignInOptions()))
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('will not accept the same passkey sign-in twice', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $authenticator = new FakeAuthenticator;
    passkeyFor($user, $authenticator);

    $credential = $authenticator->assert(passkeySignInOptions());

    resolve(SignInWithPasskeyAction::class)->handle($credential);
    auth()->logout();

    Livewire::test('pages::auth.login')
        ->call('signInWithPasskey', $credential)
        ->assertHasErrors('credential');

    $this->assertGuest();
});

it('rejects a passkey sign-in with a forged signature or the wrong origin', function (Closure $tamper): void {
    $user = User::factory()->member()->create(['active' => true]);
    $authenticator = new FakeAuthenticator;
    passkeyFor($user, $authenticator);

    Livewire::test('pages::auth.login')
        ->call('signInWithPasskey', $tamper($authenticator, passkeySignInOptions()))
        ->assertHasErrors(['credential' => __("That passkey didn't work. Please try again.")]);

    $this->assertGuest();
})->with([
    'forged signature' => [function (FakeAuthenticator $authenticator, array $options): array {
        $credential = $authenticator->assert($options);
        $credential['response']['signature'] = (new FakeAuthenticator)->assert($options)['response']['signature'];

        return $credential;
    }],
    'wrong origin' => [fn (FakeAuthenticator $authenticator, array $options): array => $authenticator->assert($options, 'https://elsewhere.example')],
]);

it('rejects a passkey that has been removed', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $authenticator = new FakeAuthenticator;
    passkeyFor($user, $authenticator)->delete();

    Livewire::test('pages::auth.login')
        ->call('signInWithPasskey', $authenticator->assert(passkeySignInOptions()))
        ->assertHasErrors('credential');

    $this->assertGuest();
});

it('keeps a disabled account out even with a valid passkey', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $authenticator = new FakeAuthenticator;
    passkeyFor($user, $authenticator);
    $user->forceFill(['active' => false])->save();

    Livewire::test('pages::auth.login')
        ->call('signInWithPasskey', $authenticator->assert(passkeySignInOptions()))
        ->assertHasErrors(['credential' => __('auth.failed')]);

    $this->assertGuest();
});

it('slows down repeated failed passkey sign-ins', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $authenticator = new FakeAuthenticator;
    passkeyFor($user, $authenticator);

    $component = Livewire::test('pages::auth.login');

    foreach (range(1, SignInWithPasskeyAction::MAX_ATTEMPTS) as $attempt) {
        $component->call('signInWithPasskey', ['id' => 'nope'])->assertHasErrors('credential');
    }

    $component->call('signInWithPasskey', $authenticator->assert(passkeySignInOptions()))
        ->assertHasErrors(['credential' => __('auth.throttle', ['seconds' => 60, 'minutes' => 1])]);

    $this->assertGuest();

    RateLimiter::clear('passkey|127.0.0.1');

    $component->call('signInWithPasskey', $authenticator->assert(passkeySignInOptions()))->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

it('clears an earlier passkey error when a new attempt starts', function (): void {
    Livewire::test('pages::auth.login')
        ->call('signInWithPasskey', ['id' => 'nope'])
        ->assertHasErrors('credential')
        ->call('passkeyOptions')
        ->assertHasNoErrors();
});

it('removes a passkey once the password checks out', function (): void {
    $user = User::factory()->member()->create(['active' => true]);
    $passkey = passkeyFor($user);
    $this->actingAs($user);

    $component = Livewire::test('shared.passkeys')
        ->assertSee('Work laptop')
        ->call('confirmRemoval', $passkey->id)
        ->assertSet('removingId', $passkey->id)
        ->set('remove_password', 'wrong-password')
        ->call('remove')
        ->assertHasErrors('remove_password');

    expect($user->passkeys()->count())->toBe(1);

    $component->set('remove_password', 'password')
        ->call('remove')
        ->assertHasNoErrors()
        ->assertSet('removingId', null)
        ->assertDontSee('Work laptop');

    expect($user->passkeys()->count())->toBe(0)
        ->and(Activity::query()->latest('id')->first()?->only(['event', 'subject_label']))
        ->toBe(['event' => ActivityEvent::PASSKEY_REMOVED, 'subject_label' => 'Work laptop']);
});

it('cannot remove someone else\'s passkey', function (): void {
    $passkey = passkeyFor(User::factory()->member()->create(['active' => true]));
    $this->actingAs(User::factory()->member()->create(['active' => true]));

    Livewire::test('shared.passkeys')
        ->call('confirmRemoval', $passkey->id)
        ->set('remove_password', 'password')
        ->call('remove')
        ->assertNotFound();

    expect(Passkey::query()->count())->toBe(1);
});

it('shows passkeys on the site account page and the admin security screen', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);
    passkeyFor($user)->forceFill(['last_used_at' => now()->subHour()])->save();
    $this->actingAs($user);

    $this->get(route('account'))->assertOk()->assertSee(__('Passkeys'))->assertSee('Work laptop');

    $this->get(route('admin.account-security'))
        ->assertOk()
        ->assertSee(__('Passkeys'))
        ->assertSee(__('Last used :time', ['time' => '1 hour ago']))
        ->assertDontSee(__('Staff need two-factor authentication. Turn it on below, or sign in with a passkey.'));
});

it('counts a passkey sign-in as two-factor when staff must use it', function (): void {
    Settings::set(['require_two_factor' => true]);
    $user = User::factory()->admin()->create(['active' => true]);
    passkeyFor($user);
    $this->actingAs($user);

    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.account-security'));
    $this->get(route('admin.account-security'))
        ->assertOk()
        ->assertSee(__('Staff need two-factor authentication. Turn it on below, or sign in with a passkey.'));

    Session::put(SignInWithPasskeyAction::SESSION_KEY, true);

    $this->get(route('admin.dashboard'))->assertOk();
});
