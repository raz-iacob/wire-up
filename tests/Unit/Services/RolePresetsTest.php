<?php

declare(strict_types=1);

use App\Services\RolePresets;

it('seeds the five default roles', function (): void {
    expect(array_column(RolePresets::all(), 'key'))
        ->toBe(['owner', 'admin', 'editor', 'author', 'member']);
});

it('finds a preset by key', function (): void {
    expect(RolePresets::find('owner'))->toMatchArray(['key' => 'owner', 'bypass' => true]);
});

it('returns null for an unknown preset key', function (): void {
    expect(RolePresets::find('does-not-exist'))->toBeNull();
});
