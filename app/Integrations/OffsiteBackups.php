<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;

final class OffsiteBackups extends CardIntegration
{
    public function key(): string
    {
        return 'offsite-backups';
    }

    public function settings(): array
    {
        return [
            'offsite_endpoint' => SettingKind::PRIVATE,
            'offsite_region' => SettingKind::PRIVATE,
            'offsite_bucket' => SettingKind::PRIVATE,
            'offsite_folder' => SettingKind::PRIVATE,
            'offsite_access_key' => SettingKind::PRIVATE,
            'offsite_secret_key' => SettingKind::SECRET,
        ];
    }

    public function connected(array $saved): bool
    {
        return $this->text($saved, 'offsite_bucket') !== ''
            && $this->text($saved, 'offsite_access_key') !== ''
            && $this->text($saved, 'offsite_secret_key') !== '';
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
