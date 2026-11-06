<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;
use App\Services\NewsletterService;

final class Newsletter extends CardIntegration
{
    protected ?array $disconnects = ['newsletter_provider', 'newsletter_api_key', 'newsletter_list_id'];

    public function key(): string
    {
        return 'newsletter';
    }

    public function settings(): array
    {
        return [
            'newsletter_provider' => SettingKind::PRIVATE,
            'newsletter_api_key' => SettingKind::SECRET,
            'newsletter_list_id' => SettingKind::PRIVATE,
            'newsletter_double_opt_in' => SettingKind::PRIVATE,
        ];
    }

    public function connected(array $saved): bool
    {
        return array_key_exists($this->text($saved, 'newsletter_provider'), NewsletterService::PROVIDERS)
            && $this->text($saved, 'newsletter_api_key') !== '';
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
