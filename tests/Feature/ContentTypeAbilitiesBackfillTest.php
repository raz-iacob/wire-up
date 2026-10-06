<?php

declare(strict_types=1);

use App\Models\RecordType;
use App\Models\Role;

function contentTypeAbilitiesBackfill(): object
{
    return require database_path('migrations/2026_10_06_202657_grant_existing_content_type_abilities_to_preset_roles.php');
}

it('grants preset roles access to content types created before the fix', function (): void {
    RecordType::factory()->create(['key' => 'product']);
    Role::query()->whereIn('key', ['admin', 'editor', 'author'])->get()
        ->each(fn (Role $role): bool => $role->update(['abilities' => ['pages.view']]));

    contentTypeAbilitiesBackfill()->up();

    expect(Role::query()->where('key', 'admin')->firstOrFail()->abilities)->toBe(['pages.view', 'records.product.view', 'records.product.create', 'records.product.edit', 'records.product.delete'])
        ->and(Role::query()->where('key', 'author')->firstOrFail()->abilities)->toBe(['pages.view', 'records.product.view', 'records.product.create', 'records.product.edit']);
});

it('keeps the abilities an owner already chose for a content type', function (): void {
    RecordType::factory()->create(['key' => 'product']);
    $editor = Role::query()->where('key', 'editor')->firstOrFail();
    $editor->update(['abilities' => ['records.product.view']]);

    contentTypeAbilitiesBackfill()->up();

    expect($editor->refresh()->abilities)->toBe(['records.product.view']);
});
