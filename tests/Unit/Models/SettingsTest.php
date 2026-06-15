<?php

declare(strict_types=1);

use App\Models\Settings;
use Illuminate\Support\Facades\Schema;

it('caches settings as a key/value map', function (): void {
    Settings::set(['site_name' => 'Acme']);

    expect(Settings::cached())->toMatchArray(['site_name' => 'Acme']);
});

it('returns an empty array when the settings table is absent', function (): void {
    Schema::shouldReceive('hasTable')->with('settings')->andReturnFalse();

    expect(Settings::cached())->toBe([]);
});
