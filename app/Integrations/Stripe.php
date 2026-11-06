<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;
use Illuminate\Support\Str;

final class Stripe extends CardIntegration
{
    public function key(): string
    {
        return 'stripe';
    }

    public function settings(): array
    {
        return [
            'stripe_publishable_key' => SettingKind::PRIVATE,
            'stripe_secret_key' => SettingKind::SECRET,
            'stripe_webhook_secret' => SettingKind::SECRET,
            'stripe_webhook_endpoint_id' => SettingKind::PRIVATE,
        ];
    }

    public function connected(array $saved): bool
    {
        return $this->text($saved, 'stripe_publishable_key') !== '';
    }

    public function configKeys(): array
    {
        return ['cashier.key', 'cashier.secret', 'cashier.webhook.secret'];
    }

    public function config(array $saved): array
    {
        $config = [];

        foreach (['key' => 'stripe_publishable_key', 'secret' => 'stripe_secret_key', 'webhook.secret' => 'stripe_webhook_secret'] as $cashierKey => $settingKey) {
            $value = $this->text($saved, $settingKey);

            if ($value !== '') {
                $config['cashier.'.$cashierKey] = $value;
            }
        }

        return $config;
    }

    public function hint(string $setting, string $saved): string
    {
        if ($setting !== 'stripe_secret_key' || $saved === '') {
            return $this->lastFour($saved);
        }

        return Str::before($saved, '_').'_'.Str::betweenFirst($saved, '_', '_').'_…'.Str::substr($saved, -4);
    }
}
