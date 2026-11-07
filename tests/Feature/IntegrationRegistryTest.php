<?php

declare(strict_types=1);

use App\Integrations\Assistant;
use App\Integrations\CardIntegration;
use App\Integrations\ErrorReports;
use App\Integrations\GoogleAnalytics;
use App\Integrations\GoogleMaps;
use App\Integrations\Integration;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Mail;
use App\Integrations\Newsletter;
use App\Integrations\Nightwatch;
use App\Integrations\OffsiteBackups;
use App\Integrations\Pexels;
use App\Integrations\Sentry;
use App\Integrations\Slack;
use App\Integrations\Stripe;
use App\Integrations\Turnstile;

it('encrypts exactly the credential settings', function (): void {
    expect(IntegrationRegistry::secretKeys())->toEqualCanonicalizing([
        'pexels_api_key', 'google_analytics_credentials', 'google_maps_api_key', 'slack_webhook_url', 'ai_api_key',
        'mail_password', 'stripe_secret_key', 'stripe_webhook_secret', 'error_report_slack_webhook_url',
        'turnstile_secret_key', 'newsletter_api_key', 'sentry_dsn', 'nightwatch_token', 'offsite_secret_key',
    ]);
});

it('leaves every credential and install-specific setting out of site exports', function (): void {
    expect(IntegrationRegistry::unexportedKeys())->toEqualCanonicalizing([
        'pexels_api_key', 'google_analytics_credentials', 'google_analytics_property_id', 'slack_webhook_url',
        'ai_provider', 'ai_api_key', 'mail_host', 'mail_username', 'mail_password', 'mail_from_address',
        'mail_from_name', 'mail_encryption', 'google_maps_api_key', 'stripe_publishable_key', 'stripe_secret_key',
        'stripe_webhook_secret', 'stripe_webhook_endpoint_id', 'error_report_email', 'error_report_slack_webhook_url',
        'turnstile_site_key', 'turnstile_secret_key', 'newsletter_provider', 'newsletter_api_key', 'newsletter_list_id',
        'newsletter_double_opt_in', 'sentry_dsn', 'sentry_traces_sample_rate', 'nightwatch_token',
        'nightwatch_ingest_uri', 'nightwatch_request_sample_rate', 'offsite_endpoint', 'offsite_region',
        'offsite_bucket', 'offsite_folder', 'offsite_access_key', 'offsite_secret_key',
    ])->and(array_diff(IntegrationRegistry::secretKeys(), IntegrationRegistry::unexportedKeys()))->toBeEmpty();
});

it('shows a saved secret only as a hint', function (Integration $integration, string $setting, string $saved, string $hint): void {
    expect($integration->hint($setting, $saved))->toBe($hint);
})->with([
    'nothing saved' => [new Pexels, 'pexels_api_key', '', ''],
    'last four' => [new Pexels, 'pexels_api_key', 'pexels-secret-9876', '…9876'],
    'stripe secret key' => [new Stripe, 'stripe_secret_key', 'sk_test_51abc1234', 'sk_test_…1234'],
    'stripe webhook secret' => [new Stripe, 'stripe_webhook_secret', 'whsec_abc5678', '…5678'],
    'service account' => [new GoogleAnalytics, 'google_analytics_credentials', '{"client_email":"reports@project.iam.gserviceaccount.com"}', 'reports@project.iam.gserviceaccount.com'],
    'unreadable key file' => [new GoogleAnalytics, 'google_analytics_credentials', 'not json', '…'],
    'slack webhook' => [new Slack, 'slack_webhook_url', 'https://hooks.slack.com/services/T0/B0/abcd', 'https://hooks.slack.com/services/…abcd'],
    'error report webhook' => [new ErrorReports, 'error_report_slack_webhook_url', 'https://hooks.slack.com/services/T0/B0/wxyz', 'https://hooks.slack.com/services/…wxyz'],
    'sentry dsn' => [new Sentry, 'sentry_dsn', 'https://key@o1.ingest.sentry.io/1', 'https://…@o1.ingest.sentry.io/1'],
    'nightwatch token' => [new Nightwatch, 'nightwatch_token', 'token-4321', '…4321'],
    'off-site secret key' => [new OffsiteBackups, 'offsite_secret_key', 'off-site-secret-2468', '…2468'],
]);

it('counts an integration as connected once its key settings are saved', function (CardIntegration $integration, array $saved): void {
    expect($integration->connected([]))->toBeFalse()
        ->and($integration->connected($saved))->toBeTrue();
})->with([
    'stripe' => [new Stripe, ['stripe_publishable_key' => 'pk_test_1']],
    'pexels' => [new Pexels, ['pexels_api_key' => 'key']],
    'google analytics' => [new GoogleAnalytics, ['google_analytics_id' => 'G-1']],
    'google maps' => [new GoogleMaps, ['google_maps_api_key' => 'key']],
    'slack' => [new Slack, ['slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/x']],
    'turnstile' => [new Turnstile, ['turnstile_secret_key' => 'secret']],
    'newsletter' => [new Newsletter, ['newsletter_provider' => 'kit', 'newsletter_api_key' => 'key']],
    'assistant' => [new Assistant, ['ai_api_key' => 'sk-ant-1']],
    'mail' => [new Mail, ['mail_password' => 'secret']],
    'off-site backups' => [new OffsiteBackups, ['offsite_bucket' => 'backups', 'offsite_access_key' => 'key', 'offsite_secret_key' => 'secret']],
]);

it('needs a known newsletter service before counting it as connected', function (): void {
    expect(new Newsletter()->connected(['newsletter_provider' => 'unknown', 'newsletter_api_key' => 'key']))->toBeFalse();
});

it('finds the integrations shown as cards by their key', function (): void {
    expect(IntegrationRegistry::find('google-analytics'))->toBeInstanceOf(GoogleAnalytics::class)
        ->and(IntegrationRegistry::find('sentry'))->toBeNull()
        ->and(IntegrationRegistry::find('unknown'))->toBeNull();
});
