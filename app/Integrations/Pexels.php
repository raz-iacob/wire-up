<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;

final class Pexels extends CardIntegration
{
    public function key(): string
    {
        return 'pexels';
    }

    public function settings(): array
    {
        return ['pexels_api_key' => SettingKind::SECRET];
    }

    public function connected(array $saved): bool
    {
        return $this->text($saved, 'pexels_api_key') !== '';
    }

    public function configKeys(): array
    {
        return ['services.pexels.key'];
    }

    public function config(array $saved): array
    {
        $key = $this->text($saved, 'pexels_api_key');

        return $key === '' ? [] : ['services.pexels.key' => $key];
    }

    public function hint(string $setting, string $saved): string
    {
        return $this->lastFour($saved);
    }
}
