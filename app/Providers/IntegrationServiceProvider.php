<?php

declare(strict_types=1);

namespace App\Providers;

use App\Integrations\IntegrationRegistry;
use App\Models\Settings;
use Illuminate\Support\ServiceProvider;

final class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->applySavedSettings();
    }

    public function applySavedSettings(): void
    {
        config()->set($this->environmentValues());

        $saved = Settings::uncached(IntegrationRegistry::settingKeys());

        foreach (IntegrationRegistry::all() as $integration) {
            config()->set($integration->config($saved));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentValues(): array
    {
        $saved = config('integrations.environment');

        if (is_array($saved)) {
            return $saved;
        }

        $environment = [];

        foreach (IntegrationRegistry::configKeys() as $configKey) {
            $environment[$configKey] = config($configKey);
        }

        config()->set('integrations.environment', $environment);

        return $environment;
    }
}
