<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;

final class Turnstile extends CardIntegration
{
    public function key(): string
    {
        return 'turnstile';
    }

    public function settings(): array
    {
        return [
            'turnstile_site_key' => SettingKind::PRIVATE,
            'turnstile_secret_key' => SettingKind::SECRET,
        ];
    }

    public function connected(array $saved): bool
    {
        return $this->text($saved, 'turnstile_secret_key') !== '';
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
