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
        $environment = $this->environmentValues();
        $settings = Settings::uncached(array_keys(self::CONFIG_KEYS));

        foreach (self::CONFIG_KEYS as $setting => $configKey) {
            config()->set($configKey, filled($settings[$setting] ?? null) ? $settings[$setting] : $environment[$configKey]);
        }

        config()->set('nightwatch.enabled', (bool) $environment['nightwatch.enabled'] && filled(config('nightwatch.token')));
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentValues(): array
    {
        $saved = config('monitoring.environment');

        if (is_array($saved)) {
            return $saved;
        }

        $environment = [];

        foreach ([...self::CONFIG_KEYS, 'nightwatch.enabled'] as $configKey) {
            $environment[$configKey] = config($configKey);
        }

        config()->set('monitoring.environment', $environment);

        return $environment;
    }
}
