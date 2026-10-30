<?php

declare(strict_types=1);

use App\Models\Role;

function activityAbilityBackfill(): object
{
    return require database_path('migrations/'.basename((string) collect(glob(database_path('migrations/*_grant_activity_ability_to_administrators.php')))->first()));
}

it('lets existing administrators see the activity log', function (): void {
    $admin = Role::query()->where('key', 'admin')->firstOrFail();
    $admin->update(['abilities' => ['pages.view']]);

    activityAbilityBackfill()->up();

    expect($admin->refresh()->abilities)->toBe(['pages.view', 'activity.view']);
});

it('leaves an administrator role that can already see the log', function (): void {
    $admin = Role::query()->where('key', 'admin')->firstOrFail();
    $admin->update(['abilities' => ['activity.view', 'pages.view']]);

    activityAbilityBackfill()->up();

    expect($admin->refresh()->abilities)->toBe(['activity.view', 'pages.view']);
});

it('does nothing on a site without an administrator role', function (): void {
    Role::query()->where('key', 'admin')->delete();

    activityAbilityBackfill()->up();

    expect(Role::query()->where('key', 'admin')->exists())->toBeFalse();
});
