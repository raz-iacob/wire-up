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

it('grants assistant.use to admin but not to editor or author', function (): void {
    $abilities = fn (string $key): array => collect(RolePresets::all())->firstWhere('key', $key)['abilities'];

    expect($abilities('admin'))->toContain('assistant.use')
        ->and($abilities('editor'))->not->toContain('assistant.use')
        ->and($abilities('author'))->not->toContain('assistant.use')
        ->and($abilities('member'))->not->toContain('assistant.use');

    expect(RolePresets::find('owner')['bypass'])->toBeTrue();
});
