<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Page;
use App\Models\Settings;
use App\Models\Submission;
use App\Models\User;
use App\Services\Turnstile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function connectTurnstile(): void
{
    Settings::set(['turnstile_site_key' => '1x00000000000000000000AA', 'turnstile_secret_key' => 'turnstile-secret-TS01']);
}

function turnstileAnswers(bool $success): void
{
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => $success, 'error-codes' => $success ? [] : ['invalid-input-response']])]);
}

function turnstileContactForm(): Testable
{
    $page = Page::factory()->create();
    $block = Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => BlockType::CONTACT_FORM,
        'content' => BlockType::CONTACT_FORM->defaultContent(),
    ]);

    return Livewire::test('site.contact-form', ['config' => [], 'blockId' => $block->id, 'pageId' => $page->id])
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('message', 'Hello there');
}

it('asks Cloudflare about the token and passes only what it accepts', function (): void {
    connectTurnstile();
    Http::fake([Turnstile::VERIFY_URL => Http::sequence()
        ->push(['success' => true])
        ->push(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    expect(resolve(Turnstile::class)->verify('good-token', '203.0.113.9'))->toBeTrue()
        ->and(resolve(Turnstile::class)->verify('', '203.0.113.9'))->toBeFalse()
        ->and(resolve(Turnstile::class)->verify('bad-token', null))->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->url() === Turnstile::VERIFY_URL
        && $request['secret'] === 'turnstile-secret-TS01'
        && $request['response'] === 'good-token'
        && $request['remoteip'] === '203.0.113.9');
});

it('lets forms through when Cloudflare cannot be reached or has a server error', function (): void {
    connectTurnstile();
    Log::spy();

    Http::fake([Turnstile::VERIFY_URL => Http::sequence()
        ->pushFailedConnection()
        ->push('down', 503)]);

    expect(resolve(Turnstile::class)->verify('token', null))->toBeTrue()
        ->and(resolve(Turnstile::class)->verify('token', null))->toBeTrue();

    Log::shouldHaveReceived('warning')->twice();
});

it('leaves forms alone while Turnstile is not connected', function (): void {
    Http::fake();

    expect(resolve(Turnstile::class)->enabled())->toBeFalse()
        ->and(resolve(Turnstile::class)->verify('', null))->toBeTrue();

    $component = turnstileContactForm()->assertDontSeeHtml('data-turnstile');

    test()->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors()->assertSet('sent', true);

    Http::assertNothingSent();
});

it('shows the widget and refuses a contact message Cloudflare rejects', function (): void {
    connectTurnstile();
    turnstileAnswers(false);

    $component = turnstileContactForm()
        ->assertSeeHtml('data-turnstile')
        ->assertSeeHtml('1x00000000000000000000AA')
        ->set('turnstileToken', 'bad-token');

    test()->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors(['form'])
        ->assertSee('Please confirm you’re human and try again.')
        ->assertSet('turnstileToken', '')
        ->assertDispatched('turnstile-reset')
        ->assertSet('sent', false);

    expect(Submission::query()->exists())->toBeFalse();
});

it('sends a contact message once Cloudflare accepts the token', function (): void {
    connectTurnstile();
    turnstileAnswers(true);

    $component = turnstileContactForm()->set('turnstileToken', 'good-token');

    test()->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors()->assertSet('sent', true);

    expect(Submission::query()->count())->toBe(1);
});

it('protects signing in, signing up and resetting a password', function (string $page, string $action, array $fields): void {
    connectTurnstile();
    turnstileAnswers(false);
    config()->set('site.allow_registration', true);

    $component = Livewire::test($page)->assertSeeHtml('data-turnstile');

    foreach ([...$fields, 'turnstileToken' => 'bad-token'] as $field => $value) {
        $component->set($field, $value);
    }

    $component->call($action)
        ->assertHasErrors(['turnstile'])
        ->assertSee('Please confirm you’re human and try again.');

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
})->with([
    'login' => ['pages::auth.login', 'login', ['email' => 'new@example.com', 'password' => 'secret']],
    'register' => ['pages::auth.register', 'register', ['name' => 'New Person', 'email' => 'new@example.com', 'password' => 'Secret-Pass-123', 'password_confirmation' => 'Secret-Pass-123']],
    'forgot password' => ['pages::auth.forgot-password', 'sendPasswordResetLink', ['email' => 'new@example.com']],
]);

it('signs in once Cloudflare accepts the token', function (): void {
    connectTurnstile();
    turnstileAnswers(true);
    $user = User::factory()->create(['email' => 'member@example.com', 'password' => bcrypt('secret'), 'active' => true, 'role' => 'member']);

    Livewire::test('pages::auth.login')
        ->set('email', $user->email)
        ->set('password', 'secret')
        ->set('turnstileToken', 'good-token')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));
});

it('connects, tests and disconnects Turnstile from the integrations screen', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-integrations')
        ->set('turnstileForm.turnstile_site_key', ' 0x4AAAAAAA-site ')
        ->set('turnstileForm.turnstile_secret_key', '0x4AAAAAAA-secret-TS02')
        ->call('connectTurnstile')
        ->assertHasNoErrors()
        ->assertSet('turnstileForm.turnstile_secret_key', '');

    expect(Settings::get('turnstile_site_key'))->toBe('0x4AAAAAAA-site')
        ->and(Settings::get('turnstile_secret_key'))->toBe('0x4AAAAAAA-secret-TS02');

    $html = $this->get(route('admin.settings-integrations'))->getContent();

    expect($html)->not->toContain('0x4AAAAAAA-secret')->and($html)->toContain('…TS02');

    Http::fake([Turnstile::VERIFY_URL => Http::sequence()
        ->push(['success' => false, 'error-codes' => ['invalid-input-response']])
        ->push(['success' => false, 'error-codes' => ['invalid-input-secret']])
        ->pushFailedConnection('offline')]);

    $component->call('testTurnstile')->assertDispatched('toast-show', toastSays('Cloudflare accepted the secret key.'));
    $component->call('testTurnstile')->assertDispatched('toast-show', toastSays('Cloudflare doesn’t recognise this secret key.'));
    $component->call('testTurnstile')->assertDispatched('toast-show', toastSays('Could not reach Cloudflare: offline'));

    $component->call('disconnect', 'turnstile');

    expect(Settings::get('turnstile_site_key'))->toBe('')
        ->and(Settings::get('turnstile_secret_key'))->toBe('')
        ->and(resolve(Turnstile::class)->enabled())->toBeFalse();
});

it('requires both keys and checks their format', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->call('connectTurnstile')
        ->assertHasErrors(['turnstileForm.turnstile_site_key' => 'required', 'turnstileForm.turnstile_secret_key' => 'required'])
        ->set('turnstileForm.turnstile_site_key', 'not a key!')
        ->set('turnstileForm.turnstile_secret_key', 'valid_secret')
        ->call('connectTurnstile')
        ->assertHasErrors(['turnstileForm.turnstile_site_key' => 'regex'])
        ->assertSee('Paste the site key exactly as Cloudflare shows it.');
});
