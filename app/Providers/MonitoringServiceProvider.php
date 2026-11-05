<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Settings;
use Illuminate\Support\ServiceProvider;

final class MonitoringServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, string>
     */
    public const array CONFIG_KEYS = [
        'sentry_dsn' => 'sentry.dsn',
        'sentry_traces_sample_rate' => 'sentry.traces_sample_rate',
        'nightwatch_token' => 'nightwatch.token',
        'nightwatch_ingest_uri' => 'nightwatch.ingest.uri',
        'nightwatch_request_sample_rate' => 'nightwatch.sampling.requests',
    ];

    public function register(): void
    {
        $this->applySavedSettings();
    }

    public function applySavedSettings(): void
    {
        $settings = Settings::uncached(array_keys(self::CONFIG_KEYS));

        foreach (self::CONFIG_KEYS as $setting => $configKey) {
            if (filled($settings[$setting] ?? null)) {
                config()->set($configKey, $settings[$setting]);
            }
        }

        config()->set('nightwatch.enabled', (bool) config('nightwatch.enabled') && filled(config('nightwatch.token')));
    }
}
