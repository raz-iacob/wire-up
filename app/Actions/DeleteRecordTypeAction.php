<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RecordType;
use App\Models\Role;
use RuntimeException;

final readonly class DeleteRecordTypeAction
{
    public function handle(RecordType $recordType): void
    {
        throw_if($recordType->isInUse(), RuntimeException::class, 'Cannot delete a content type that still has records.');

        $recordType->delete();

        $prefix = 'records.'.$recordType->key.'.';

        Role::query()->get()
            ->filter(fn (Role $role): bool => array_any($role->abilities, fn (string $ability): bool => str_starts_with($ability, $prefix)))
            ->each(fn (Role $role): bool => $role->update([
                'abilities' => array_values(array_filter($role->abilities, fn (string $ability): bool => ! str_starts_with($ability, $prefix))),
            ]));
    }
}
