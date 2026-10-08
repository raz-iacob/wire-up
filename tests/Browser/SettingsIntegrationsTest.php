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
