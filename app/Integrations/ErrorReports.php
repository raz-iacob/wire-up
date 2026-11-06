<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;
use Illuminate\Support\Str;

final class ErrorReports extends Integration
{
    public function settings(): array
    {
        return [
            'error_report_email' => SettingKind::PRIVATE,
            'error_report_slack_webhook_url' => SettingKind::SECRET,
        ];
    }

    public function hint(string $setting, string $saved): string
    {
        return $saved === '' ? '' : 'https://hooks.slack.com/services/…'.Str::substr($saved, -4);
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
