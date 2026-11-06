<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;
use App\Services\AiModelCatalog;

final class Assistant extends CardIntegration
{
    protected ?array $disconnects = ['ai_api_key'];

    public function key(): string
    {
        return 'assistant';
    }

    public function settings(): array
    {
        return [
            'ai_provider' => SettingKind::PRIVATE,
            'ai_api_key' => SettingKind::SECRET,
            'ai_model' => SettingKind::EXPORTED,
        ];
    }

    public function connected(array $saved): bool
    {
        return $this->text($saved, 'ai_api_key') !== '';
    }

    public function configKeys(): array
    {
        return [
            'ai.default',
            ...array_map(fn (string $provider): string => 'ai.providers.'.$provider.'.key', array_keys(AiModelCatalog::PROVIDERS)),
        ];
    }

    public function config(array $saved): array
    {
        $provider = $this->text($saved, 'ai_provider');
        $key = $this->text($saved, 'ai_api_key');

        if (! array_key_exists($provider, AiModelCatalog::PROVIDERS) || $key === '') {
            return [];
        }

        return ['ai.default' => $provider, 'ai.providers.'.$provider.'.key' => $key];
    }

    public function hint(string $setting, string $saved): string
    {
        return $this->lastFour($saved);
    }
}
