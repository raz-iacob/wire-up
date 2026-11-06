<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Providers\IntegrationServiceProvider;
use Laravel\Nightwatch\NightwatchServiceProvider;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;

it('bridges the saved pexels key into the services config at boot', function (): void {
    Settings::set(['pexels_api_key' => 'db-pexels-key']);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('services.pexels.key'))->toBe('db-pexels-key');
});

it('falls back to the env pexels key when none is saved', function (): void {
    Settings::set(['pexels_api_key' => '']);
    config()->set(['integrations.environment' => null, 'services.pexels.key' => 'env-pexels-key']);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('services.pexels.key'))->toBe('env-pexels-key');
});

it('bridges the saved slack webhook into the services config at boot', function (): void {
    Settings::set(['slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/db']);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('services.slack.webhook_url'))->toBe('https://hooks.slack.com/services/T0/B0/db');
});

it('falls back to the env slack webhook when none is saved', function (): void {
    Settings::set(['slack_webhook_url' => '']);
    config()->set(['integrations.environment' => null, 'services.slack.webhook_url' => 'https://hooks.slack.com/services/T0/B0/env']);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('services.slack.webhook_url'))->toBe('https://hooks.slack.com/services/T0/B0/env');
});

it('bridges the saved analytics credentials into the services config at boot', function (): void {
    Settings::set([
        'google_analytics_property_id' => '123456789',
        'google_analytics_credentials' => '{"client_email":"a@b.c","private_key":"k"}',
    ]);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('services.google_analytics.property_id'))->toBe('123456789')
        ->and(config('services.google_analytics.credentials'))->toBe('{"client_email":"a@b.c","private_key":"k"}');
});

it('falls back to the env analytics credentials when none are saved', function (): void {
    Settings::set(['google_analytics_property_id' => '', 'google_analytics_credentials' => '']);
    config()->set(['integrations.environment' => null, 'services.google_analytics.property_id' => 'env-property', 'services.google_analytics.credentials' => 'env-credentials']);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('services.google_analytics.property_id'))->toBe('env-property')
        ->and(config('services.google_analytics.credentials'))->toBe('env-credentials');
});

it('bridges the saved gemini key into the ai config at boot', function (): void {
    Settings::set(['ai_provider' => 'gemini', 'ai_api_key' => 'gemini-db-key']);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('ai.default'))->toBe('gemini')
        ->and(config('ai.providers.gemini.key'))->toBe('gemini-db-key');
});

it('bridges the saved AI provider key into the ai config at boot', function (): void {
    Settings::set(['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-ant-db']);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('ai.default'))->toBe('anthropic')
        ->and(config('ai.providers.anthropic.key'))->toBe('sk-ant-db');
});

it('does not touch the ai config when no assistant key is saved', function (): void {
    Settings::set(['ai_api_key' => '']);
    config()->set(['integrations.environment' => null, 'ai.default' => 'openai']);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('ai.default'))->toBe('openai');
});

it('bridges the saved email settings into the mail config at boot', function (): void {
    Settings::set([
        'mail_host' => 'smtp.example.com',
        'mail_port' => 587,
        'mail_username' => 'user',
        'mail_password' => 'secret',
        'mail_encryption' => 'tls',
        'mail_from_address' => 'hi@example.com',
        'mail_from_name' => 'Example Site',
    ]);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.username'))->toBe('user')
        ->and(config('mail.mailers.smtp.password'))->toBe('secret')
        ->and(config('mail.mailers.smtp.scheme'))->toBeNull()
        ->and(config('mail.from.address'))->toBe('hi@example.com')
        ->and(config('mail.from.name'))->toBe('Example Site');
});

it('uses the smtps scheme for ssl and falls back to the app name for the from name', function (): void {
    Settings::set([
        'mail_host' => 'smtp.resend.com',
        'mail_port' => 465,
        'mail_username' => 'resend',
        'mail_password' => 'secret',
        'mail_encryption' => 'ssl',
        'mail_from_address' => 'hi@example.com',
        'mail_from_name' => '',
    ]);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.from.name'))->toBe(config('app.name'));
});

it('does not touch the mail config when no email settings are saved', function (): void {
    Settings::set(['mail_host' => '', 'mail_password' => '']);
    config()->set(['integrations.environment' => null, 'mail.default' => 'log']);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('mail.default'))->toBe('log');
});

it('turns a disconnected integration off even when the configuration is cached', function (): void {
    config()->set([
        'integrations.environment' => null,
        'services.pexels.key' => null,
        'services.slack.webhook_url' => null,
        'ai.default' => 'openai',
        'ai.providers.anthropic.key' => null,
        'cashier.secret' => null,
        'mail.default' => 'log',
        'mail.mailers.smtp.host' => '127.0.0.1',
    ]);
    Settings::set([
        'pexels_api_key' => 'db-pexels-key',
        'slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/db',
        'ai_provider' => 'anthropic',
        'ai_api_key' => 'sk-ant-db',
        'stripe_secret_key' => 'sk_test_db',
        'mail_host' => 'smtp.example.com',
        'mail_username' => 'user',
        'mail_password' => 'secret',
        'mail_from_address' => 'hi@example.com',
    ]);
    new IntegrationServiceProvider(app())->applySavedSettings();
    $cached = config()->all();

    expect(config('services.slack.webhook_url'))->toBe('https://hooks.slack.com/services/T0/B0/db')
        ->and(config('mail.default'))->toBe('smtp');

    config()->set($cached);
    Settings::set(['pexels_api_key' => '', 'slack_webhook_url' => '', 'ai_api_key' => '', 'stripe_secret_key' => '', 'mail_host' => '']);
    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('services.pexels.key'))->toBeNull()
        ->and(config('services.slack.webhook_url'))->toBeNull()
        ->and(config('ai.default'))->toBe('openai')
        ->and(config('ai.providers.anthropic.key'))->toBeNull()
        ->and(config('cashier.secret'))->toBeNull()
        ->and(config('mail.default'))->toBe('log')
        ->and(config('mail.mailers.smtp.host'))->toBe('127.0.0.1');
});

it('points Sentry and Nightwatch at the saved settings and turns Nightwatch on once a token is saved', function (): void {
    config()->set(['integrations.environment' => null, 'nightwatch.enabled' => true]);
    Settings::set([
        'sentry_dsn' => 'https://saved@o1.ingest.sentry.io/1',
        'sentry_traces_sample_rate' => 0.2,
        'nightwatch_token' => 'saved-token',
        'nightwatch_ingest_uri' => '127.0.0.1:2410',
        'nightwatch_request_sample_rate' => 0.0,
    ]);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('sentry.dsn'))->toBe('https://saved@o1.ingest.sentry.io/1')
        ->and(config('sentry.traces_sample_rate'))->toBe(0.2)
        ->and(config('nightwatch.token'))->toBe('saved-token')
        ->and(config('nightwatch.ingest.uri'))->toBe('127.0.0.1:2410')
        ->and(config('nightwatch.sampling.requests'))->toEqual(0.0)
        ->and(config('nightwatch.enabled'))->toBeTrue()
        ->and(app()->getProviders(NightwatchServiceProvider::class))->not->toBeEmpty()
        ->and(app()->getProviders(SentryServiceProvider::class))->not->toBeEmpty();
});

it('falls back to the environment when nothing is saved and keeps Nightwatch off without a token', function (?string $environmentToken, bool $environmentEnabled, bool $enabled): void {
    config()->set(['integrations.environment' => null, 'sentry.dsn' => 'https://env@o1.ingest.sentry.io/1', 'nightwatch.enabled' => $environmentEnabled, 'nightwatch.token' => $environmentToken]);

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('sentry.dsn'))->toBe('https://env@o1.ingest.sentry.io/1')
        ->and(config('nightwatch.token'))->toBe($environmentToken)
        ->and(config('nightwatch.enabled'))->toBe($enabled);
})->with([
    'no token' => [null, true, false],
    'token in the environment' => ['env-token', true, true],
    'turned off in the environment' => ['env-token', false, false],
]);

it('applies saved changes on top of a cached configuration', function (): void {
    config()->set(['integrations.environment' => null, 'sentry.dsn' => null, 'nightwatch.enabled' => true, 'nightwatch.token' => null]);
    Settings::set(['sentry_dsn' => 'https://saved@o1.ingest.sentry.io/1']);
    new IntegrationServiceProvider(app())->applySavedSettings();
    $cached = config()->all();

    expect(config('sentry.dsn'))->toBe('https://saved@o1.ingest.sentry.io/1')
        ->and(config('nightwatch.enabled'))->toBeFalse();

    config()->set($cached);
    Settings::set(['sentry_dsn' => '', 'nightwatch_token' => 'new-token']);
    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('sentry.dsn'))->toBeNull()
        ->and(config('nightwatch.token'))->toBe('new-token')
        ->and(config('nightwatch.enabled'))->toBeTrue();
});
