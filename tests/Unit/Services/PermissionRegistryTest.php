<?php

declare(strict_types=1);

use App\Models\RecordType;
use App\Services\PermissionRegistry;
use Illuminate\Support\Facades\Schema;

it('exposes the static resources with their applicable actions', function (): void {
    $resources = collect(PermissionRegistry::resources())->keyBy('key');

    expect($resources->get('pages')['actions'])->toBe(['view', 'create', 'edit', 'delete']);
    expect($resources->get('settings')['actions'])->toBe(['view', 'edit']);
    expect($resources->get('inbox')['actions'])->toBe(['view', 'delete']);
});

it('adds a resource per record type', function (): void {
    RecordType::factory()->create(['key' => 'product', 'slug_prefix' => 'products', 'name' => 'Products']);

    $resources = collect(PermissionRegistry::resources())->keyBy('key');

    expect($resources->has('records.product'))->toBeTrue();
    expect($resources->get('records.product')['label'])->toBe('Products');
    expect($resources->get('records.product')['group'])->toBe(PermissionRegistry::GROUP_RECORDS);
});

it('omits record resources when the record types table is missing', function (): void {
    Schema::shouldReceive('hasTable')->andReturnFalse();

    $keys = collect(PermissionRegistry::resources())->pluck('key');

    expect($keys)->toContain('pages')->not->toContain('records.product');
});

it('builds dotted ability keys for every resource action', function (): void {
    $keys = PermissionRegistry::abilityKeys();

    expect($keys)->toContain('pages.view', 'pages.delete', 'settings.edit', 'roles.create')
        ->not->toContain('settings.delete', 'inbox.create');

    expect(PermissionRegistry::isValidAbility('pages.view'))->toBeTrue();
    expect(PermissionRegistry::isValidAbility('pages.publish'))->toBeFalse();
});

it('registers the assistant.use ability under administration', function (): void {
    $assistant = collect(PermissionRegistry::resources())->firstWhere('key', 'assistant');

    expect($assistant)->not->toBeNull()
        ->and($assistant['group'])->toBe(PermissionRegistry::GROUP_ADMINISTRATION)
        ->and($assistant['actions'])->toBe(['use'])
        ->and(PermissionRegistry::abilityKeys())->toContain('assistant.use')
        ->and(PermissionRegistry::isValidAbility('assistant.use'))->toBeTrue();
});
