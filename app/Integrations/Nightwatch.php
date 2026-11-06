<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;

final class Nightwatch extends Integration
{
    private const array CONFIG = [
        'nightwatch_token' => 'nightwatch.token',
        'nightwatch_ingest_uri' => 'nightwatch.ingest.uri',
        'nightwatch_request_sample_rate' => 'nightwatch.sampling.requests',
    ];

    public function settings(): array
    {
        return [
            'nightwatch_token' => SettingKind::SECRET,
            'nightwatch_ingest_uri' => SettingKind::PRIVATE,
            'nightwatch_request_sample_rate' => SettingKind::PRIVATE,
        ];
    }

    public function configKeys(): array
    {
        return [...array_values(self::CONFIG), 'nightwatch.enabled'];
    }

    public function config(array $saved): array
    {
        $config = [];

        foreach (self::CONFIG as $setting => $configKey) {
            if (filled($saved[$setting] ?? null)) {
                $config[$configKey] = $saved[$setting];
            }
        }

        $config['nightwatch.enabled'] = (bool) config('nightwatch.enabled') && filled($config['nightwatch.token'] ?? config('nightwatch.token'));

        return $config;
    }

    public function hint(string $setting, string $saved): string
    {
        return $this->lastFour($saved);
    }
}
