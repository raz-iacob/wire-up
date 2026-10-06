<?php

declare(strict_types=1);

use App\Actions\GrantRecordTypeAbilitiesAction;
use App\Models\Role;

function roleAbilities(string $key): array
{
    return Role::query()->where('key', $key)->firstOrFail()->abilities;
}

it('grants each preset role its actions on a new content type', function (): void {
    $custom = Role::factory()->create(['abilities' => ['pages.view']]);

    resolve(GrantRecordTypeAbilitiesAction::class)->handle('product');

    expect(roleAbilities('admin'))->toContain('records.product.view', 'records.product.create', 'records.product.edit', 'records.product.delete')
        ->and(roleAbilities('editor'))->toContain('records.product.view', 'records.product.create', 'records.product.edit', 'records.product.delete')
        ->and(roleAbilities('author'))->toContain('records.product.view', 'records.product.create', 'records.product.edit')
        ->and(roleAbilities('author'))->not->toContain('records.product.delete')
        ->and(roleAbilities('member'))->toBe([])
        ->and($custom->refresh()->abilities)->toBe(['pages.view']);
});

it('leaves a role alone once it holds any ability for that content type', function (): void {
    $editor = Role::query()->where('key', 'editor')->firstOrFail();
    $editor->update(['abilities' => ['pages.view', 'records.product.view']]);

    resolve(GrantRecordTypeAbilitiesAction::class)->handle('product');

    expect($editor->refresh()->abilities)->toBe(['pages.view', 'records.product.view']);
});
