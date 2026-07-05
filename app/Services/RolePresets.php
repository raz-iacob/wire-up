<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PermissionAction;

final class RolePresets
{
    /**
     * @return array<int, array{key: string, name: string, abilities: array<int, string>, bypass: bool, is_protected: bool}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'owner',
                'name' => 'Owner',
                'abilities' => [],
                'bypass' => true,
                'is_protected' => true,
            ],
            [
                'key' => 'admin',
                'name' => 'Administrator',
                'abilities' => PermissionRegistry::abilityKeys(),
                'bypass' => false,
                'is_protected' => false,
            ],
            [
                'key' => 'editor',
                'name' => 'Editor',
                'abilities' => self::editorAbilities(),
                'bypass' => false,
                'is_protected' => false,
            ],
            [
                'key' => 'author',
                'name' => 'Author',
                'abilities' => self::authorAbilities(),
                'bypass' => false,
                'is_protected' => false,
            ],
            [
                'key' => 'member',
                'name' => 'Member',
                'abilities' => [],
                'bypass' => false,
                'is_protected' => true,
            ],
        ];
    }

    /**
     * @return array{key: string, name: string, abilities: array<int, string>, bypass: bool, is_protected: bool}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $preset) {
            if ($preset['key'] === $key) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function editorAbilities(): array
    {
        $abilities = [];

        foreach (PermissionRegistry::resources() as $resource) {
            $abilities = match ($resource['group']) {
                PermissionRegistry::GROUP_CONTENT, PermissionRegistry::GROUP_RECORDS => [
                    ...$abilities,
                    ...array_map(fn (string $action): string => $resource['key'].'.'.$action, $resource['actions']),
                ],
                PermissionRegistry::GROUP_COMMUNICATION => [
                    ...$abilities,
                    $resource['key'].'.'.PermissionAction::View->value,
                    $resource['key'].'.'.PermissionAction::Delete->value,
                ],
                default => $abilities,
            };
        }

        return $abilities;
    }

    /**
     * @return array<int, string>
     */
    private static function authorAbilities(): array
    {
        $abilities = [];
        $actions = [PermissionAction::View->value, PermissionAction::Create->value, PermissionAction::Edit->value];

        foreach (PermissionRegistry::resources() as $resource) {
            if ($resource['group'] !== PermissionRegistry::GROUP_RECORDS) {
                continue;
            }

            foreach ($actions as $action) {
                $abilities[] = $resource['key'].'.'.$action;
            }
        }

        return $abilities;
    }
}
