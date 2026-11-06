<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;

final class GoogleMaps extends CardIntegration
{
    public function key(): string
    {
        return 'google-maps';
    }

    public function settings(): array
    {
        return ['google_maps_api_key' => SettingKind::SECRET];
    }

    public function connected(array $saved): bool
    {
        return $this->text($saved, 'google_maps_api_key') !== '';
    }

    public function configKeys(): array
    {
        return [];
    }

    public function config(array $saved): array
    {
        return [];
    }

    public function hint(string $setting, string $saved): string
    {
        return $this->lastFour($saved);
    }
}
