<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Role;
use App\Services\RolePresets;

final readonly class GrantRecordTypeAbilitiesAction
{
    public function handle(string $recordTypeKey): void
    {
        $prefix = 'records.'.$recordTypeKey.'.';

        Role::query()
            ->whereIn('key', RolePresets::RECORD_TYPE_GRANTEES)
            ->get()
            ->reject(fn (Role $role): bool => array_any($role->abilities, fn (string $ability): bool => str_starts_with($ability, $prefix)))
            ->each(fn (Role $role): bool => $role->update([
                'abilities' => [
                    ...$role->abilities,
                    ...array_map(fn (string $action): string => $prefix.$action, RolePresets::recordTypeActions($role->key)),
                ],
            ]));
    }
}
