<?php

declare(strict_types=1);

use App\Actions\AddPasskeyAction;
use App\Actions\StartPasskeyRegistrationAction;
use App\Models\User;
use Tests\Support\FakeAuthenticator;

/**
 * @param  array<string, mixed>|null  $seed
 */
function installFakeAuthenticator(mixed $page, ?array $seed = null): void
{
    $origin = (string) $page->script('location.origin');

    config([
        'passkeys.allowed_origins' => [$origin],
        'passkeys.relying_party_id' => parse_url($origin, PHP_URL_HOST),
    ]);

    $script = (string) file_get_contents(base_path('tests/Fixtures/fake-authenticator.js'));

    expect($page->script('('.$script.')('.json_encode($seed).')'))->toBeTrue();
}

function adminWithBrowserPasskey(mixed $page): User
{
    $user = User::factory()->admin()->create(['active' => true]);
    $authenticator = new FakeAuthenticator;

    installFakeAuthenticator($page);

    resolve(AddPasskeyAction::class)->handle(
        $user,
        'Laptop',
        $authenticator->register(resolve(StartPasskeyRegistrationAction::class)->handle($user)),
    );

    installFakeAuthenticator($page, $authenticator->export());

    return $user;
}

it('adds a passkey from the admin security screen', function (): void {
    $user = User::factory()->admin()->create(['active' => true]);
    $this->actingAs($user);

    $page = visit(route('admin.account-security'));
    installFakeAuthenticator($page);

    $page->click('Add a passkey')
        ->type('name', 'Office laptop')
        ->type('password', 'password')
        ->press('Continue')
        ->assertSee('Passkey added.')
        ->assertSee('Office laptop')
        ->assertNoJavascriptErrors();

    expect($user->passkeys()->sole()->name)->toBe('Office laptop');
});

it('signs in with a passkey from the button', function (): void {
    $page = visit(route('login'));
    $user = adminWithBrowserPasskey($page);

    $page->press('Sign in with a passkey')
        ->assertPathIs('/admin')
        ->assertNoJavascriptErrors();

    $this->assertAuthenticatedAs($user);
});

it('signs in when a passkey is picked from the email field suggestions', function (): void {
    $page = visit(route('login'));
    $user = adminWithBrowserPasskey($page);

    $picked = $page->script(<<<'JS'
        (async () => {
            Alpine.$data(document.querySelector('[x-data^="passkeySignIn"]')).listen();

            for (let attempt = 0; attempt < 50; attempt++) {
                if (window.fakePasskey.waitingForAutofill()) {
                    window.fakePasskey.pickFromAutofill();

                    return true;
                }

                await new Promise((resolve) => setTimeout(resolve, 100));
            }

            return false;
        })()
        JS);

    expect($picked)->toBeTrue();

    $page->assertPathIs('/admin')->assertNoJavascriptErrors();

    $this->assertAuthenticatedAs($user);
});
