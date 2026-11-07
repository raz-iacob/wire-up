<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;

final class RemoteMcp extends Integration
{
    public function settings(): array
    {
        return [
            'remote_mcp_enabled' => SettingKind::PRIVATE,
        ];
    }

    public function hint(string $setting, string $saved): string
    {
        return '';
    }

    public function configKeys(): array
    {
        return [];
    }

    public function config(array $saved): array
    {
        return [];
    }
}
