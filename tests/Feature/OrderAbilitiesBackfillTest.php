<?php

declare(strict_types=1);

use App\Models\Role;

function orderAbilitiesBackfill(): object
{
    return require database_path('migrations/'.basename((string) collect(glob(database_path('migrations/*_grant_order_abilities_to_administrators.php')))->first()));
}

it('lets existing administrators view and fulfil orders', function (): void {
    $admin = Role::query()->where('key', 'admin')->firstOrFail();
    $admin->update(['abilities' => ['pages.view']]);

    orderAbilitiesBackfill()->up();

    expect($admin->refresh()->abilities)->toBe(['pages.view', 'orders.view', 'orders.edit']);
});

it('leaves an administrator role whose order access was already chosen', function (): void {
    $admin = Role::query()->where('key', 'admin')->firstOrFail();
    $admin->update(['abilities' => ['orders.view']]);

    orderAbilitiesBackfill()->up();

    expect($admin->refresh()->abilities)->toBe(['orders.view']);
});

it('does nothing on a site without an administrator role', function (): void {
    Role::query()->where('key', 'admin')->delete();

    orderAbilitiesBackfill()->up();

    expect(Role::query()->where('key', 'admin')->exists())->toBeFalse();
});
