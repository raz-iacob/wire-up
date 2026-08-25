<?php

declare(strict_types=1);

use App\Models\Settings;
use Illuminate\Support\Facades\Schema;

it('caches settings as a key/value map', function (): void {
    Settings::set(['site_name' => 'Acme']);

    expect(Settings::cached())->toMatchArray(['site_name' => 'Acme']);
});

it('boots without the cache table so artisan runs before the first migration', function (): void {
    config(['cache.default' => 'database']);
    app()->forgetInstance('cache');
    app()->forgetInstance('cache.store');
    Schema::dropIfExists('cache');

    expect(Settings::cached())->toBe([]);
});

it('returns an empty array when the database is unreachable', function (): void {
    Schema::shouldReceive('hasTable')->andThrow(unreachableDatabase());

    expect(Settings::cached())->toBe([]);
});

it('returns an empty array when the settings table is absent', function (): void {
    Schema::shouldReceive('hasTable')->with('settings')->andReturnFalse();

    expect(Settings::cached())->toBe([]);
});
