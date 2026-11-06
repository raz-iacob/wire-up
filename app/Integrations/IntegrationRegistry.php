<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;

final class IntegrationRegistry
{
    /**
     * @var list<Integration>|null
     */
    private static ?array $integrations = null;

    /**
     * @var list<string>|null
     */
    private static ?array $secretKeys = null;

    /**
     * @return list<Integration>
     */
    public static function all(): array
    {
        return self::$integrations ??= [
            new Stripe,
            new Pexels,
            new GoogleAnalytics,
            new GoogleMaps,
            new Slack,
            new Turnstile,
            new Newsletter,
            new Assistant,
            new Mail,
            new ErrorReports,
            new Sentry,
            new Nightwatch,
        ];
    }

    public static function find(string $key): ?CardIntegration
    {
        foreach (self::cards() as $integration) {
            if ($integration->key() === $key) {
                return $integration;
            }
        }

        return null;
    }

    /**
     * @return list<CardIntegration>
     */
    public static function cards(): array
    {
        return array_values(array_filter(self::all(), fn (Integration $integration): bool => $integration instanceof CardIntegration));
    }

    /**
     * @return list<string>
     */
    public static function secretKeys(): array
    {
        return self::$secretKeys ??= array_merge(...array_map(fn (Integration $integration): array => $integration->secrets(), self::all()));
    }

    /**
     * @return list<string>
     */
    public static function unexportedKeys(): array
    {
        return self::keysWhere(fn (SettingKind $kind): bool => $kind !== SettingKind::EXPORTED);
    }

    /**
     * @return list<string>
     */
    public static function settingKeys(): array
    {
        return self::keysWhere(fn (): bool => true);
    }

    /**
     * @return list<string>
     */
    public static function configKeys(): array
    {
        return array_merge(...array_map(fn (Integration $integration): array => $integration->configKeys(), self::all()));
    }

    /**
     * @param  callable(SettingKind): bool  $matches
     * @return list<string>
     */
    private static function keysWhere(callable $matches): array
    {
        $keys = [];

        foreach (self::all() as $integration) {
            foreach ($integration->settings() as $key => $kind) {
                if ($matches($kind)) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }
}
