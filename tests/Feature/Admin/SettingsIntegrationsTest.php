<?php

declare(strict_types=1);

use App\Ai\Agents\ConnectionCheck;
use App\Integrations\IntegrationRegistry;
use App\Mail\IntegrationTestMail;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('can render the integrations settings screen with a card for every integration', function (): void {
    $response = $this->actingAsAdmin()
        ->get(route('admin.settings-integrations'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-integrations');

    foreach (IntegrationRegistry::cards() as $integration) {
        $response->assertSeeLivewire('admin.integrations.'.$integration->key());
    }
});

it('redirects authenticated non-admin users away from integrations settings', function (): void {
    $nonAdmin = User::factory()->create(['active' => true, 'role' => 'member']);

    $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.settings-integrations'))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from integrations settings', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.settings-integrations'))
        ->assertRedirectToRoute('login');
});

it('hydrates the saved custom code on mount', function (): void {
    Settings::set([
        'head_scripts' => '<script>head()</script>',
        'body_scripts' => '<script>body()</script>',
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->assertSet('customCodeForm.head_scripts', '<script>head()</script>')
        ->assertSet('customCodeForm.body_scripts', '<script>body()</script>');
});

it('never sends a saved secret back to the browser, only a hint of it', function (): void {
    Settings::set([
        'pexels_api_key' => 'saved-pexels-key-PX01',
        'google_analytics_id' => 'G-SAVED01',
        'google_analytics_property_id' => '987654321',
        'google_analytics_credentials' => '{"client_email":"saved@example.com","private_key":"saved-private-key"}',
        'google_maps_api_key' => 'saved-maps-key-MP02',
        'slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/saved-SL03',
        'ai_provider' => 'anthropic',
        'ai_api_key' => 'sk-ant-saved-AI04',
        'mail_host' => 'smtp.example.com',
        'mail_password' => 'saved-mail-password-ML05',
        'stripe_publishable_key' => 'pk_test_saved',
        'stripe_secret_key' => 'sk_test_saved-ST06',
    ]);

    $this->actingAsAdmin();

    $html = $this->get(route('admin.settings-integrations'))->assertOk()->getContent();

    expect($html)->not->toContain('saved-pexels-key')
        ->and($html)->not->toContain('saved-private-key')
        ->and($html)->not->toContain('saved-maps-key')
        ->and($html)->not->toContain('B0/saved')
        ->and($html)->not->toContain('sk-ant-saved')
        ->and($html)->not->toContain('saved-mail-password')
        ->and($html)->not->toContain('sk_test_saved')
        ->and($html)->toContain('…PX01')
        ->and($html)->toContain('saved@example.com')
        ->and($html)->toContain('…MP02')
        ->and($html)->toContain('https://hooks.slack.com/services/…SL03')
        ->and($html)->toContain('…AI04')
        ->and($html)->toContain('…ML05')
        ->and($html)->toContain('sk_test_…ST06');

    foreach (['pexels' => 'pexels_api_key', 'google-analytics' => 'google_analytics_credentials', 'google-maps' => 'google_maps_api_key', 'slack' => 'slack_webhook_url', 'assistant' => 'ai_api_key', 'mail' => 'mail_password'] as $integration => $field) {
        Livewire::test('admin.integrations.'.$integration)->assertSet('form.'.$field, '');
    }

    Livewire::test('admin.integrations.pexels')->assertSee('Leave blank to keep the saved key.');
});

it('keeps the saved secret when its field is left blank', function (string $integration, array $saved, array $typed, string $key): void {
    Settings::set($saved);

    $this->actingAsAdmin();

    $component = Livewire::test('admin.integrations.'.$integration);

    foreach ($typed as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('connect')->assertHasNoErrors();

    expect(Settings::get($key))->toBe($saved[$key]);
})->with([
    'pexels' => ['pexels', ['pexels_api_key' => 'saved-pexels'], [], 'pexels_api_key'],
    'google maps' => ['google-maps', ['google_maps_api_key' => 'saved-maps'], [], 'google_maps_api_key'],
    'slack' => ['slack', ['slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/saved'], [], 'slack_webhook_url'],
    'newsletter' => ['newsletter', ['newsletter_provider' => 'kit', 'newsletter_api_key' => 'saved-kit', 'newsletter_list_id' => '311'], [], 'newsletter_api_key'],
    'cloudflare turnstile' => ['turnstile', ['turnstile_site_key' => 'site-key', 'turnstile_secret_key' => 'saved-turnstile'], ['form.turnstile_site_key' => 'site-key'], 'turnstile_secret_key'],
    'google analytics reports' => ['google-analytics', ['google_analytics_id' => 'G-SAVED01', 'google_analytics_property_id' => '987654321', 'google_analytics_credentials' => '{"client_email":"a@b.c","private_key":"k"}'], [], 'google_analytics_credentials'],
    'ai assistant' => ['assistant', ['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-ant-saved', 'ai_model' => 'claude-opus-5-5'], [], 'ai_api_key'],
    'email' => ['mail', ['mail_provider' => 'custom', 'mail_host' => 'smtp.example.com', 'mail_port' => 587, 'mail_username' => 'user', 'mail_password' => 'saved-password', 'mail_encryption' => 'tls', 'mail_from_address' => 'hi@example.com', 'mail_from_name' => 'Site'], [], 'mail_password'],
]);

it('tests the saved credentials when the secret field is left blank', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
    Mail::fake();
    ConnectionCheck::fake(['OK']);

    Settings::set([
        'slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/saved',
        'ai_provider' => 'anthropic',
        'ai_api_key' => 'sk-ant-saved',
        'mail_provider' => 'custom',
        'mail_host' => 'smtp.example.com',
        'mail_password' => 'saved-password',
    ]);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.slack')
        ->call('test')
        ->assertHasNoErrors();

    Livewire::test('admin.integrations.assistant')
        ->set('form.ai_model', 'claude-sonnet-5')
        ->call('test')
        ->assertHasNoErrors();

    Livewire::test('admin.integrations.mail')
        ->set('form.mail_username', 'user@example.com')
        ->set('form.mail_from_address', 'hello@example.com')
        ->set('form.mail_from_name', 'Example Site')
        ->call('test')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.slack.com/services/T0/B0/saved');
    ConnectionCheck::assertPrompted('ping');
    Mail::assertSent(IntegrationTestMail::class);
});

it('persists and trims the custom head and body code on update', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('customCodeForm.head_scripts', "  <script>fbq('init')</script>  ")
        ->set('customCodeForm.body_scripts', '  <script>chat()</script>  ')
        ->call('updateCustomCode')
        ->assertHasNoErrors();

    expect(Settings::get('head_scripts'))->toBe("<script>fbq('init')</script>")
        ->and(Settings::get('body_scripts'))->toBe('<script>chat()</script>');
});

it('disconnects an integration by clearing its credential', function (): void {
    Settings::set([
        'pexels_api_key' => 'existing',
        'google_analytics_id' => 'G-EXIST01',
        'google_analytics_property_id' => '987654321',
        'google_analytics_credentials' => '{"client_email":"a@b.c","private_key":"k"}',
        'google_maps_api_key' => 'existing-maps',
        'slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/existing',
    ]);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.pexels')
        ->call('disconnect')
        ->assertHasNoErrors()
        ->assertSet('form.pexels_api_key', '');

    Livewire::test('admin.integrations.google-analytics')
        ->call('disconnect')
        ->assertSet('form.google_analytics_id', '')
        ->assertSet('form.google_analytics_property_id', '')
        ->assertSet('form.google_analytics_credentials', '')
        ->assertDispatched('integrations-updated');

    Livewire::test('admin.integrations.google-maps')
        ->call('disconnect')
        ->assertSet('form.google_maps_api_key', '');

    Livewire::test('admin.integrations.slack')
        ->call('disconnect')
        ->assertSet('form.slack_webhook_url', '');

    expect(Settings::get('pexels_api_key'))->toBe('')
        ->and(Settings::get('google_analytics_id'))->toBe('')
        ->and(Settings::get('google_analytics_property_id'))->toBe('')
        ->and(Settings::get('google_analytics_credentials'))->toBe('')
        ->and(Settings::get('google_maps_api_key'))->toBe('')
        ->and(Settings::get('slack_webhook_url'))->toBe('');
});

it('injects the custom head and body code into the public site', function (): void {
    Settings::set([
        'head_scripts' => '<script>window.headMarker=1</script>',
        'body_scripts' => '<script>window.bodyMarker=1</script>',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<script>window.headMarker=1</script>', false)
        ->assertSee('<script>window.bodyMarker=1</script>', false);
});

it('omits the custom code wrappers when nothing is configured', function (): void {
    Settings::set(['head_scripts' => '', 'body_scripts' => '']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('window.headMarker', false)
        ->assertDontSee('window.bodyMarker', false);
});
