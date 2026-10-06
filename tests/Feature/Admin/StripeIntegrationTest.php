<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\StripeService;
use Illuminate\Support\Facades\URL;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;

function fakeStripeAccount(): void
{
    stripe()->respond('GET', '/v1/account', [
        'id' => 'acct_123',
        'object' => 'account',
        'business_profile' => ['name' => 'Acme Studio'],
    ]);
}

function fakeStripeWebhookEndpoints(array $existing = []): void
{
    stripe()
        ->respond('GET', '/v1/webhook_endpoints', ['object' => 'list', 'data' => $existing, 'has_more' => false, 'url' => '/v1/webhook_endpoints'])
        ->respond('POST', '/v1/webhook_endpoints', ['id' => 'we_new', 'object' => 'webhook_endpoint', 'secret' => 'whsec_created'])
        ->respond('DELETE', '/v1/webhook_endpoints/*', ['id' => 'we_old', 'object' => 'webhook_endpoint', 'deleted' => true]);
}

it('connects stripe and registers the webhook when the site is public', function (): void {
    URL::forceRootUrl('https://8.8.8.8');
    fakeStripeAccount();
    fakeStripeWebhookEndpoints([
        ['id' => 'we_stale', 'object' => 'webhook_endpoint', 'url' => 'https://8.8.8.8/stripe/webhook'],
        ['id' => 'we_other', 'object' => 'webhook_endpoint', 'url' => 'https://elsewhere.example/hook'],
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('stripeForm.stripe_publishable_key', '  pk_test_abc  ')
        ->set('stripeForm.stripe_secret_key', '  sk_test_secret1234  ')
        ->call('connectStripe')
        ->assertHasNoErrors()
        ->assertSet('stripeForm.stripe_secret_key', '');

    expect(Settings::get('stripe_publishable_key'))->toBe('pk_test_abc')
        ->and(Settings::get('stripe_secret_key'))->toBe('sk_test_secret1234')
        ->and(Settings::get('stripe_webhook_secret'))->toBe('whsec_created')
        ->and(Settings::get('stripe_webhook_endpoint_id'))->toBe('we_new');

    stripe()->assertSent('DELETE', '/v1/webhook_endpoints/we_stale');
    stripe()->assertNotSent('DELETE', '/v1/webhook_endpoints/we_other');
    stripe()->assertSent('POST', '/v1/webhook_endpoints', fn (array $params): bool => $params['url'] === 'https://8.8.8.8/stripe/webhook'
        && $params['enabled_events'] === StripeService::WEBHOOK_EVENTS
        && $params['api_version'] === Cashier::STRIPE_VERSION);
});

it('uses the pasted signing secret when the site is not publicly reachable', function (): void {
    URL::forceRootUrl('https://127.0.0.1');
    fakeStripeAccount();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('stripeForm.stripe_publishable_key', 'pk_test_abc')
        ->set('stripeForm.stripe_secret_key', 'sk_test_secret1234')
        ->set('stripeForm.stripe_webhook_secret', 'whsec_pasted')
        ->call('connectStripe')
        ->assertHasNoErrors()
        ->assertSee('https://127.0.0.1/stripe/webhook');

    expect(Settings::get('stripe_webhook_secret'))->toBe('whsec_pasted')
        ->and(Settings::get('stripe_webhook_endpoint_id'))->toBe('');

    stripe()->assertNotSent('POST', '/v1/webhook_endpoints');
});

it('never sends the saved secret key back to the browser and keeps it when left blank', function (): void {
    URL::forceRootUrl('https://127.0.0.1');
    Settings::set([
        'stripe_publishable_key' => 'pk_live_saved',
        'stripe_secret_key' => 'sk_live_savedsecret9876',
        'stripe_webhook_secret' => 'whsec_saved',
    ]);
    fakeStripeAccount();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->assertSet('stripeForm.stripe_publishable_key', 'pk_live_saved')
        ->assertSet('stripeForm.stripe_secret_key', '')
        ->assertDontSee('sk_live_savedsecret9876')
        ->assertSee('sk_live_…9876')
        ->assertSee(__('Live'))
        ->call('connectStripe')
        ->assertHasNoErrors();

    expect(Settings::get('stripe_secret_key'))->toBe('sk_live_savedsecret9876')
        ->and(Settings::get('stripe_webhook_secret'))->toBe('whsec_saved');
});

it('replaces the previously registered webhook on reconnect', function (): void {
    URL::forceRootUrl('https://8.8.8.8');
    Settings::set([
        'stripe_publishable_key' => 'pk_test_saved',
        'stripe_secret_key' => 'sk_test_saved',
        'stripe_webhook_secret' => 'whsec_saved',
        'stripe_webhook_endpoint_id' => 'we_previous',
    ]);
    fakeStripeAccount();
    fakeStripeWebhookEndpoints();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->assertSee(__('Stripe sends payment updates to this site automatically.'))
        ->call('connectStripe')
        ->assertHasNoErrors();

    stripe()->assertSent('DELETE', '/v1/webhook_endpoints/we_previous');
    expect(Settings::get('stripe_webhook_endpoint_id'))->toBe('we_new');
});

it('requires a secret key when none is saved', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('stripeForm.stripe_publishable_key', 'pk_test_abc')
        ->call('connectStripe')
        ->assertHasErrors(['stripeForm.stripe_secret_key' => __('Enter your Stripe secret key.')]);

    stripe()->assertNothingSent();
});

it('refuses to mix test and live keys', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('stripeForm.stripe_publishable_key', 'pk_test_abc')
        ->set('stripeForm.stripe_secret_key', 'sk_live_secret')
        ->call('connectStripe')
        ->assertHasErrors(['stripeForm.stripe_secret_key' => __('Both keys must be test keys, or both live keys.')]);

    stripe()->assertNothingSent();
});

it('reports the reason stripe rejected the key and saves nothing', function (): void {
    stripe()->fail('GET', '/v1/account', 'Invalid API Key provided', 401);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('stripeForm.stripe_publishable_key', 'pk_test_abc')
        ->set('stripeForm.stripe_secret_key', 'sk_test_wrong')
        ->call('connectStripe')
        ->assertHasErrors('stripeForm.stripe_secret_key')
        ->assertSee('Invalid API Key provided');

    expect(Settings::get('stripe_secret_key'))->toBeNull();
});

it('validates the stripe key formats', function (string $field, string $value): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('stripeForm.stripe_publishable_key', 'pk_test_abc')
        ->set('stripeForm.stripe_secret_key', 'sk_test_abc')
        ->set('stripeForm.'.$field, $value)
        ->call('connectStripe')
        ->assertHasErrors(['stripeForm.'.$field => 'regex']);
})->with([
    'publishable key' => ['stripe_publishable_key', 'sk_test_abc'],
    'secret key' => ['stripe_secret_key', 'pk_test_abc'],
    'signing secret' => ['stripe_webhook_secret', 'secret_abc'],
]);

it('forbids connecting stripe without the settings edit ability', function (): void {
    $editor = User::factory()->editor()->create(['active' => true]);

    $this->actingAs($editor);

    Livewire::test('pages::admin.settings-integrations')
        ->set('stripeForm.stripe_publishable_key', 'pk_test_abc')
        ->set('stripeForm.stripe_secret_key', 'sk_test_abc')
        ->call('connectStripe')
        ->assertForbidden();

    expect(Settings::get('stripe_secret_key'))->toBeNull();
});

it('disconnects stripe and removes the webhook it registered', function (): void {
    Settings::set([
        'stripe_publishable_key' => 'pk_test_saved',
        'stripe_secret_key' => 'sk_test_saved',
        'stripe_webhook_secret' => 'whsec_saved',
        'stripe_webhook_endpoint_id' => 'we_saved',
    ]);
    stripe()->respond('DELETE', '/v1/webhook_endpoints/we_saved', ['id' => 'we_saved', 'object' => 'webhook_endpoint', 'deleted' => true]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->call('disconnectStripe')
        ->assertSet('stripeForm.stripe_publishable_key', '');

    stripe()->assertSent('DELETE', '/v1/webhook_endpoints/we_saved');

    foreach (['stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret', 'stripe_webhook_endpoint_id'] as $key) {
        expect(Settings::get($key))->toBe('');
    }
});

it('still disconnects when stripe cannot remove the webhook', function (): void {
    Settings::set([
        'stripe_publishable_key' => 'pk_test_saved',
        'stripe_secret_key' => 'sk_test_revoked',
        'stripe_webhook_endpoint_id' => 'we_saved',
    ]);
    stripe()->fail('DELETE', '/v1/webhook_endpoints/we_saved', 'Expired API Key provided', 401);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->call('disconnectStripe');

    expect(Settings::get('stripe_secret_key'))->toBe('');
});

it('disconnects a manually configured stripe without calling stripe', function (): void {
    Settings::set([
        'stripe_publishable_key' => 'pk_test_saved',
        'stripe_secret_key' => 'sk_test_saved',
        'stripe_webhook_secret' => 'whsec_pasted',
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->call('disconnectStripe');

    stripe()->assertNothingSent();
    expect(Settings::get('stripe_webhook_secret'))->toBe('');
});

it('bridges the saved stripe keys and the site currency into the cashier config at boot', function (): void {
    Settings::set([
        'stripe_publishable_key' => 'pk_test_db',
        'stripe_secret_key' => 'sk_test_db',
        'stripe_webhook_secret' => 'whsec_db',
        'currency' => 'EUR',
    ]);

    new AppServiceProvider(app())->boot();

    expect(config('cashier.key'))->toBe('pk_test_db')
        ->and(config('cashier.secret'))->toBe('sk_test_db')
        ->and(config('cashier.webhook.secret'))->toBe('whsec_db')
        ->and(config('cashier.currency'))->toBe('eur');
});
