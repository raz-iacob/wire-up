<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;
use Illuminate\Support\Str;

final class Sentry extends Integration
{
    private const array CONFIG = [
        'sentry_dsn' => 'sentry.dsn',
        'sentry_traces_sample_rate' => 'sentry.traces_sample_rate',
    ];

    public function settings(): array
    {
        return [
            'sentry_dsn' => SettingKind::SECRET,
            'sentry_traces_sample_rate' => SettingKind::PRIVATE,
        ];
    }

    public function configKeys(): array
    {
        return array_values(self::CONFIG);
    }

    public function config(array $saved): array
    {
        $config = [];

        foreach (self::CONFIG as $setting => $configKey) {
            if (filled($saved[$setting] ?? null)) {
                $config[$configKey] = $saved[$setting];
            }
        }

        return $config;
    }

    public function hint(string $setting, string $saved): string
    {
        return $saved === '' ? '' : 'https://…@'.Str::after($saved, '@');
    }
}
