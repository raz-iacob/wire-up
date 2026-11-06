<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;

final class GoogleAnalytics extends CardIntegration
{
    public function key(): string
    {
        return 'google-analytics';
    }

    public function settings(): array
    {
        return [
            'google_analytics_id' => SettingKind::EXPORTED,
            'google_analytics_property_id' => SettingKind::PRIVATE,
            'google_analytics_credentials' => SettingKind::SECRET,
        ];
    }

    public function connected(array $saved): bool
    {
        return $this->text($saved, 'google_analytics_id') !== '';
    }

    public function configKeys(): array
    {
        return ['services.google_analytics.property_id', 'services.google_analytics.credentials'];
    }

    public function config(array $saved): array
    {
        return array_filter([
            'services.google_analytics.property_id' => $this->text($saved, 'google_analytics_property_id'),
            'services.google_analytics.credentials' => $this->text($saved, 'google_analytics_credentials'),
        ], fn (string $value): bool => $value !== '');
    }

    public function hint(string $setting, string $saved): string
    {
        if ($setting !== 'google_analytics_credentials' || $saved === '') {
            return $this->lastFour($saved);
        }

        $email = data_get(json_decode($saved, true), 'client_email');

        return is_string($email) && $email !== '' ? $email : '…';
    }
}
