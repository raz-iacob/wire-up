<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PermissionAction;
use App\Models\RecordType;
use Illuminate\Support\Facades\Schema;

final class PermissionRegistry
{
    public const string GROUP_CONTENT = 'content';

    public const string GROUP_RECORDS = 'records';

    public const string GROUP_COMMUNICATION = 'communication';

    public const string GROUP_ADMINISTRATION = 'administration';

    /**
     * @return array<int, array{key: string, label: string, group: string, actions: array<int, string>}>
     */
    public static function resources(): array
    {
        return [...self::staticResources(), ...self::recordResources()];
    }

    /**
     * @return array<int, string>
     */
    public static function abilityKeys(): array
    {
        $keys = [];

        foreach (self::resources() as $resource) {
            foreach ($resource['actions'] as $action) {
                $keys[] = $resource['key'].'.'.$action;
            }
        }

        return $keys;
    }

    public static function isValidAbility(string $ability): bool
    {
        return in_array($ability, self::abilityKeys(), true);
    }

    /**
     * @return array<int, array{key: string, label: string, group: string, actions: array<int, string>}>
     */
    private static function staticResources(): array
    {
        $crud = array_map(fn (PermissionAction $action): string => $action->value, PermissionAction::cases());

        return [
            ['key' => 'pages', 'label' => __('Pages'), 'group' => self::GROUP_CONTENT, 'actions' => $crud],
            ['key' => 'categories', 'label' => __('Categories'), 'group' => self::GROUP_CONTENT, 'actions' => $crud],
            ['key' => 'inbox', 'label' => __('Inbox'), 'group' => self::GROUP_COMMUNICATION, 'actions' => [PermissionAction::View->value, PermissionAction::Delete->value]],
            ['key' => 'users', 'label' => __('Users'), 'group' => self::GROUP_ADMINISTRATION, 'actions' => $crud],
            ['key' => 'settings', 'label' => __('Settings'), 'group' => self::GROUP_ADMINISTRATION, 'actions' => [PermissionAction::View->value, PermissionAction::Edit->value]],
            ['key' => 'roles', 'label' => __('Roles'), 'group' => self::GROUP_ADMINISTRATION, 'actions' => $crud],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, group: string, actions: array<int, string>}>
     */
    private static function recordResources(): array
    {
        if (! Schema::hasTable('record_types')) {
            return [];
        }

        $crud = array_map(fn (PermissionAction $action): string => $action->value, PermissionAction::cases());

        return RecordType::query()
            ->orderBy('position')
            ->get()
            ->map(fn (RecordType $type): array => [
                'key' => 'records.'.$type->key,
                'label' => $type->name,
                'group' => self::GROUP_RECORDS,
                'actions' => $crud,
            ])
            ->all();
    }
}
