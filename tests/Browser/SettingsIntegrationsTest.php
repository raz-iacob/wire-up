<?php

declare(strict_types=1);

use App\Models\Settings;

it('saves a model name typed into the assistant model box', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-integrations'));

    $page->assertNoJavascriptErrors()
        ->click('[x-on\:click*="integration-assistant"] button')
        ->type('[data-modal="integration-assistant"] input[type="password"]', 'sk-ant-typed')
        ->click('[data-modal="integration-assistant"] ui-select input')
        ->assertSee('claude-sonnet-5-5 — balanced')
        ->type('[data-modal="integration-assistant"] ui-select input', 'claude-custom-9')
        ->click('[data-flux-option-create]')
        ->click('[data-modal="integration-assistant"] button[type="submit"]')
        ->assertSee('AI Assistant connected.')
        ->assertNoJavascriptErrors();

    expect(Settings::get('ai_model'))->toBe('claude-custom-9');
});

it('connects an integration from its own card and window', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-integrations'));

    $page->assertNoJavascriptErrors()
        ->click('[x-on\:click*="integration-pexels"] button')
        ->type('[data-modal="integration-pexels"] input[type="password"]', 'pexels-typed-key')
        ->click('[data-modal="integration-pexels"] button[type="submit"]')
        ->assertSee('Pexels connected.')
        ->assertSee('Connected')
        ->assertNoJavascriptErrors();

    expect(Settings::get('pexels_api_key'))->toBe('pexels-typed-key');
});

it('does not warn about unsaved custom code after closing an integration window without saving', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-integrations'));

    $page->click('[x-on\:click*="integration-pexels"] button')
        ->type('[data-modal="integration-pexels"] input[type="password"]', 'typed-but-not-saved');

    $page->script("Flux.modal('integration-pexels').close(); window.leaveWarnings = []; window.confirm = (message) => { window.leaveWarnings.push(message); return false; }; window.Livewire.navigate('/admin'); void 0");

    $page->assertSee('Unread messages')
        ->assertScript('window.leaveWarnings.length', 0)
        ->assertNoJavascriptErrors();
});
