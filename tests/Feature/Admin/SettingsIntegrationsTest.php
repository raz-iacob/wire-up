<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Livewire\Livewire;

it('can render the integrations settings screen', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.settings-integrations'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-integrations');
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

it('hydrates the saved credentials and custom code on mount', function (): void {
    Settings::set([
        'pexels_api_key' => 'saved-pexels-key',
        'google_analytics_id' => 'G-SAVED01',
        'google_analytics_property_id' => '987654321',
        'google_analytics_credentials' => '{"client_email":"saved@example.com","private_key":"saved-key"}',
        'google_maps_api_key' => 'saved-maps-key',
        'slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/saved',
        'head_scripts' => '<script>head()</script>',
        'body_scripts' => '<script>body()</script>',
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->assertSet('pexels_api_key', 'saved-pexels-key')
        ->assertSet('google_analytics_id', 'G-SAVED01')
        ->assertSet('google_analytics_property_id', '987654321')
        ->assertSet('google_analytics_credentials', '{"client_email":"saved@example.com","private_key":"saved-key"}')
        ->assertSet('google_maps_api_key', 'saved-maps-key')
        ->assertSet('slack_webhook_url', 'https://hooks.slack.com/services/T0/B0/saved')
        ->assertSet('head_scripts', '<script>head()</script>')
        ->assertSet('body_scripts', '<script>body()</script>');
});

it('persists and trims the custom head and body code on update', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('head_scripts', "  <script>fbq('init')</script>  ")
        ->set('body_scripts', '  <script>chat()</script>  ')
        ->call('updateCustomCode')
        ->assertHasNoErrors();

    expect(Settings::get('head_scripts'))->toBe("<script>fbq('init')</script>")
        ->and(Settings::get('body_scripts'))->toBe('<script>chat()</script>');
});

it('connects pexels by persisting the api key', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('pexels_api_key', 'new-pexels-key')
        ->call('connectPexels')
        ->assertHasNoErrors();

    expect(Settings::get('pexels_api_key'))->toBe('new-pexels-key');
});

it('connects google analytics by persisting the measurement id', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_analytics_id', 'G-NEW0001')
        ->call('connectGoogleAnalytics')
        ->assertHasNoErrors();

    expect(Settings::get('google_analytics_id'))->toBe('G-NEW0001')
        ->and(Settings::get('google_analytics_property_id'))->toBe('')
        ->and(Settings::get('google_analytics_credentials'))->toBe('');
});

it('connects google analytics reports by persisting the property id and credentials', function (): void {
    $this->actingAsAdmin();

    $credentials = '{"client_email":"reports@example.com","private_key":"a-key"}';

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_analytics_id', 'G-NEW0001')
        ->set('google_analytics_property_id', '123456789')
        ->set('google_analytics_credentials', $credentials)
        ->call('connectGoogleAnalytics')
        ->assertHasNoErrors()
        ->assertDispatched('integrations-updated');

    expect(Settings::get('google_analytics_id'))->toBe('G-NEW0001')
        ->and(Settings::get('google_analytics_property_id'))->toBe('123456789')
        ->and(Settings::get('google_analytics_credentials'))->toBe($credentials);
});

it('requires the analytics reports fields together', function (string $field, string $value, string $missing): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_analytics_id', 'G-NEW0001')
        ->set($field, $value)
        ->call('connectGoogleAnalytics')
        ->assertHasErrors([$missing => 'required_with']);
})->with([
    'property id without credentials' => ['google_analytics_property_id', '123456789', 'google_analytics_credentials'],
    'credentials without property id' => ['google_analytics_credentials', '{"client_email":"a@b.c","private_key":"k"}', 'google_analytics_property_id'],
]);

it('rejects a non-numeric property id', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_analytics_id', 'G-NEW0001')
        ->set('google_analytics_property_id', 'G-NEW0001')
        ->set('google_analytics_credentials', '{"client_email":"a@b.c","private_key":"k"}')
        ->call('connectGoogleAnalytics')
        ->assertHasErrors(['google_analytics_property_id'])
        ->assertSee('Enter the numeric GA4 property ID, like 123456789.');
});

it('rejects credentials that are not a valid service account key', function (string $credentials): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_analytics_id', 'G-NEW0001')
        ->set('google_analytics_property_id', '123456789')
        ->set('google_analytics_credentials', $credentials)
        ->call('connectGoogleAnalytics')
        ->assertHasErrors(['google_analytics_credentials'])
        ->assertSee('Paste the full service account JSON key file.');
})->with([
    'not json' => ['not-json'],
    'missing client email' => ['{"private_key":"k"}'],
    'missing private key' => ['{"client_email":"a@b.c"}'],
    'empty client email' => ['{"client_email":"","private_key":"k"}'],
]);

it('connects google maps by persisting the api key', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_maps_api_key', 'AIzaMapsKey123')
        ->call('connectGoogleMaps')
        ->assertHasNoErrors();

    expect(Settings::get('google_maps_api_key'))->toBe('AIzaMapsKey123');
});

it('requires an api key to connect google maps', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_maps_api_key', '')
        ->call('connectGoogleMaps')
        ->assertHasErrors(['google_maps_api_key']);
});

it('connects slack by persisting the webhook url', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('slack_webhook_url', '  https://hooks.slack.com/services/T0/B0/xyz  ')
        ->call('connectSlack')
        ->assertHasNoErrors();

    expect(Settings::get('slack_webhook_url'))->toBe('https://hooks.slack.com/services/T0/B0/xyz');
});

it('rejects webhook urls that are not slack incoming webhooks', function (string $url, string $rule): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('slack_webhook_url', $url)
        ->call('connectSlack')
        ->assertHasErrors(['slack_webhook_url' => $rule]);
})->with([
    'empty' => ['', 'required'],
    'not a url' => ['not-a-url', 'url'],
    'not a slack webhook' => ['https://example.com/webhook', 'regex'],
]);

it('requires an api key to connect pexels', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('pexels_api_key', '')
        ->call('connectPexels')
        ->assertHasErrors(['pexels_api_key']);
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

    Livewire::test('pages::admin.settings-integrations')
        ->call('disconnect', 'pexels')
        ->assertHasNoErrors()
        ->assertSet('pexels_api_key', '')
        ->call('disconnect', 'google-analytics')
        ->assertSet('google_analytics_id', '')
        ->assertSet('google_analytics_property_id', '')
        ->assertSet('google_analytics_credentials', '')
        ->assertDispatched('integrations-updated')
        ->call('disconnect', 'google-maps')
        ->assertSet('google_maps_api_key', '')
        ->call('disconnect', 'slack')
        ->assertSet('slack_webhook_url', '');

    expect(Settings::get('pexels_api_key'))->toBe('')
        ->and(Settings::get('google_analytics_id'))->toBe('')
        ->and(Settings::get('google_analytics_property_id'))->toBe('')
        ->and(Settings::get('google_analytics_credentials'))->toBe('')
        ->and(Settings::get('google_maps_api_key'))->toBe('')
        ->and(Settings::get('slack_webhook_url'))->toBe('');
});

it('validates the google analytics id format', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_analytics_id', 'UA-12345')
        ->call('connectGoogleAnalytics')
        ->assertHasErrors(['google_analytics_id'])
        ->assertSee('Enter a valid Google Analytics measurement ID, like G-XXXXXXXXXX.');
});

it('bridges the saved pexels key into the services config at boot', function (): void {
    Settings::set(['pexels_api_key' => 'db-pexels-key']);

    new AppServiceProvider(app())->boot();

    expect(config('services.pexels.key'))->toBe('db-pexels-key');
});

it('falls back to the env pexels key when none is saved', function (): void {
    Settings::set(['pexels_api_key' => '']);
    config()->set('services.pexels.key', 'env-pexels-key');

    new AppServiceProvider(app())->boot();

    expect(config('services.pexels.key'))->toBe('env-pexels-key');
});

it('bridges the saved slack webhook into the services config at boot', function (): void {
    Settings::set(['slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/db']);

    new AppServiceProvider(app())->boot();

    expect(config('services.slack.webhook_url'))->toBe('https://hooks.slack.com/services/T0/B0/db');
});

it('falls back to the env slack webhook when none is saved', function (): void {
    Settings::set(['slack_webhook_url' => '']);
    config()->set('services.slack.webhook_url', 'https://hooks.slack.com/services/T0/B0/env');

    new AppServiceProvider(app())->boot();

    expect(config('services.slack.webhook_url'))->toBe('https://hooks.slack.com/services/T0/B0/env');
});

it('bridges the saved analytics credentials into the services config at boot', function (): void {
    Settings::set([
        'google_analytics_property_id' => '123456789',
        'google_analytics_credentials' => '{"client_email":"a@b.c","private_key":"k"}',
    ]);

    new AppServiceProvider(app())->boot();

    expect(config('services.google_analytics.property_id'))->toBe('123456789')
        ->and(config('services.google_analytics.credentials'))->toBe('{"client_email":"a@b.c","private_key":"k"}');
});

it('falls back to the env analytics credentials when none are saved', function (): void {
    Settings::set(['google_analytics_property_id' => '', 'google_analytics_credentials' => '']);
    config()->set('services.google_analytics.property_id', 'env-property');
    config()->set('services.google_analytics.credentials', 'env-credentials');

    new AppServiceProvider(app())->boot();

    expect(config('services.google_analytics.property_id'))->toBe('env-property')
        ->and(config('services.google_analytics.credentials'))->toBe('env-credentials');
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

it('hydrates the saved AI Assistant settings on mount', function (): void {
    Settings::set([
        'ai_provider' => 'anthropic',
        'ai_api_key' => 'sk-ant-saved',
        'ai_model' => 'claude-sonnet-5',
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->assertSet('ai_provider', 'anthropic')
        ->assertSet('ai_api_key', 'sk-ant-saved')
        ->assertSet('ai_model', 'claude-sonnet-5');
});

it('defaults the AI Assistant to anthropic and opus when unset', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->assertSet('ai_provider', 'anthropic')
        ->assertSet('ai_model', 'claude-opus-4-8');
});

it('connects the AI Assistant by persisting provider, key and model', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('ai_provider', 'anthropic')
        ->set('ai_api_key', '  sk-ant-new  ')
        ->set('ai_model', '  claude-opus-4-8  ')
        ->call('connectAssistant')
        ->assertHasNoErrors();

    expect(Settings::get('ai_provider'))->toBe('anthropic')
        ->and(Settings::get('ai_api_key'))->toBe('  sk-ant-new  ')
        ->and(Settings::get('ai_model'))->toBe('claude-opus-4-8');
});

it('requires an api key and a known provider to connect the AI Assistant', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('ai_provider', 'cohere')
        ->set('ai_api_key', '')
        ->set('ai_model', 'x')
        ->call('connectAssistant')
        ->assertHasErrors(['ai_provider', 'ai_api_key']);
});

it('connects the AI Assistant with the gemini provider', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('ai_provider', 'gemini')
        ->set('ai_api_key', 'gemini-key')
        ->set('ai_model', 'gemini-model-name')
        ->call('connectAssistant')
        ->assertHasNoErrors();

    expect(Settings::get('ai_provider'))->toBe('gemini')
        ->and(Settings::get('ai_model'))->toBe('gemini-model-name');
});

it('bridges the saved gemini key into the ai config at boot', function (): void {
    Settings::set(['ai_provider' => 'gemini', 'ai_api_key' => 'gemini-db-key']);

    new AppServiceProvider(app())->boot();

    expect(config('ai.default'))->toBe('gemini')
        ->and(config('ai.providers.gemini.key'))->toBe('gemini-db-key');
});

it('disconnects the AI Assistant by clearing only the api key', function (): void {
    Settings::set(['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-ant-x', 'ai_model' => 'claude-opus-4-8']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->call('disconnect', 'assistant')
        ->assertHasNoErrors()
        ->assertSet('ai_api_key', '');

    expect(Settings::get('ai_api_key'))->toBe('')
        ->and(Settings::get('ai_model'))->toBe('claude-opus-4-8');
});

it('bridges the saved AI provider key into the ai config at boot', function (): void {
    Settings::set(['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-ant-db']);

    new AppServiceProvider(app())->boot();

    expect(config('ai.default'))->toBe('anthropic')
        ->and(config('ai.providers.anthropic.key'))->toBe('sk-ant-db');
});

it('does not touch the ai config when no assistant key is saved', function (): void {
    Settings::set(['ai_api_key' => '']);
    config()->set('ai.default', 'openai');

    new AppServiceProvider(app())->boot();

    expect(config('ai.default'))->toBe('openai');
});
