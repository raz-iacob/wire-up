<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;
use Illuminate\Support\Str;

final class Slack extends CardIntegration
{
    public function key(): string
    {
        return 'slack';
    }

    public function settings(): array
    {
        return ['slack_webhook_url' => SettingKind::SECRET];
    }

    public function connected(array $saved): bool
    {
        return $this->text($saved, 'slack_webhook_url') !== '';
    }

    public function configKeys(): array
    {
        return ['services.slack.webhook_url'];
    }

    public function config(array $saved): array
    {
        $url = $this->text($saved, 'slack_webhook_url');

        return $url === '' ? [] : ['services.slack.webhook_url' => $url];
    }

    public function hint(string $setting, string $saved): string
    {
        return $saved === '' ? '' : 'https://hooks.slack.com/services/…'.Str::substr($saved, -4);
    }
}
