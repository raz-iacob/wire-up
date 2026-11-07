<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Models\User;
use Livewire\Livewire;

it('opens only for developers, as a Developer tab', function (): void {
    $this->actingAs(User::factory()->owner()->create(['active' => true]));

    $this->get(route('admin.settings-remote-mcp'))->assertForbidden();

    $this->actingAs(User::factory()->developer()->create(['active' => true]));

    $this->get(route('admin.settings-developer'))->assertOk()->assertSee(route('admin.settings-remote-mcp'));
    $this->get(route('admin.settings-remote-mcp'))->assertOk()->assertSee(url('/mcp'));
});

it('turns remote MCP on and off', function (): void {
    $this->actingAs(User::factory()->developer()->create(['active' => true]));

    $component = Livewire::test('pages::admin.settings-remote-mcp')
        ->assertSet('remote_mcp_enabled', false)
        ->set('remote_mcp_enabled', true)
        ->call('update')
        ->assertDispatched('toast-show', toastSays('Remote MCP is on.'));

    expect(Settings::get('remote_mcp_enabled'))->toBeTrue();

    $component->set('remote_mcp_enabled', false)
        ->call('update')
        ->assertDispatched('toast-show', toastSays('Remote MCP is off.'));

    expect(Settings::get('remote_mcp_enabled'))->toBeFalse();
});
