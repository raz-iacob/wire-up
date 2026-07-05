<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

it('resolves abilities and honours the bypass flag', function (): void {
    $editor = Role::factory()->create(['abilities' => ['pages.view', 'pages.edit'], 'bypass' => false]);

    expect($editor->hasAbility('pages.view'))->toBeTrue();
    expect($editor->hasAbility('pages.delete'))->toBeFalse();

    $owner = Role::factory()->create(['abilities' => [], 'bypass' => true]);

    expect($owner->hasAbility('anything.at.all'))->toBeTrue();
});

it('grants admin access to bypass or ability-holding roles only', function (): void {
    expect(Role::factory()->create(['abilities' => [], 'bypass' => true])->canAccessAdmin())->toBeTrue();
    expect(Role::factory()->create(['abilities' => ['pages.view'], 'bypass' => false])->canAccessAdmin())->toBeTrue();
    expect(Role::factory()->create(['abilities' => [], 'bypass' => false])->canAccessAdmin())->toBeFalse();
});

it('reports whether it is assigned to any user', function (): void {
    $role = Role::factory()->create();

    expect($role->isInUse())->toBeFalse();

    User::factory()->for($role)->create();

    expect($role->fresh()->isInUse())->toBeTrue();
});
