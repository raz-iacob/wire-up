<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

function developerRoleMigration(): object
{
    return require database_path('migrations/'.basename((string) collect(glob(database_path('migrations/*_add_developer_role.php')))->first()));
}

beforeEach(function (): void {
    Role::query()->where('key', Role::DEVELOPER)->delete();
});

it('adds the developer role to an existing site and makes the first owner its developer', function (): void {
    $first = User::factory()->owner()->create();
    $second = User::factory()->owner()->create();
    $editor = User::factory()->admin()->create();

    developerRoleMigration()->up();

    $developer = Role::query()->where('key', Role::DEVELOPER)->sole();

    expect($developer->only(['name', 'bypass', 'is_protected', 'abilities']))->toBe(['name' => 'Developer', 'bypass' => true, 'is_protected' => true, 'abilities' => []])
        ->and($first->refresh()->role_id)->toBe($developer->id)
        ->and($second->refresh()->role->key)->toBe('owner')
        ->and($editor->refresh()->role->key)->toBe('admin');
});

it('leaves everyone alone when a developer already exists', function (): void {
    developerRoleMigration()->up();
    $owner = User::factory()->owner()->create();
    $developer = User::factory()->developer()->create();

    developerRoleMigration()->up();

    expect(Role::query()->where('key', Role::DEVELOPER)->count())->toBe(1)
        ->and($owner->refresh()->role->key)->toBe('owner')
        ->and($developer->refresh()->isDeveloper())->toBeTrue();
});

it('only adds the role on a site without owners', function (): void {
    $editor = User::factory()->admin()->create();

    developerRoleMigration()->up();

    expect(Role::query()->where('key', Role::DEVELOPER)->exists())->toBeTrue()
        ->and($editor->refresh()->role->key)->toBe('admin');
});
