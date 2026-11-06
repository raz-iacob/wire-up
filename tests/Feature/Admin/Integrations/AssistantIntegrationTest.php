<?php

declare(strict_types=1);

use App\Ai\Agents\ConnectionCheck;
use App\Models\Settings;
use App\Providers\IntegrationServiceProvider;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('asks for a new AI key when the provider changes', function (): void {
    Settings::set(['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-ant-saved']);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->set('form.ai_provider', 'openai')
        ->call('connect')
        ->assertHasErrors(['form.ai_api_key' => 'required']);

    expect(Settings::get('ai_provider'))->toBe('anthropic');
});

it('hydrates the saved AI Assistant settings on mount', function (): void {
    Settings::set([
        'ai_provider' => 'anthropic',
        'ai_api_key' => 'sk-ant-saved',
        'ai_model' => 'claude-sonnet-5',
    ]);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->assertSet('form.ai_provider', 'anthropic')
        ->assertSet('form.ai_api_key', '')
        ->assertSet('form.ai_model', 'claude-sonnet-5');
});

it('defaults the AI Assistant to anthropic and opus when unset', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->assertSet('form.ai_provider', 'anthropic')
        ->assertSet('form.ai_model', 'claude-opus-5-5');
});

it('offers the newest claude models once an anthropic key is entered', function (): void {
    Http::fake(['api.anthropic.com/v1/models*' => Http::response(['data' => [['id' => 'claude-next-6', 'display_name' => 'Claude Next 6']]])]);
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->assertSee('claude-opus-5-5 — most capable')
        ->set('form.ai_api_key', 'sk-ant-typed')
        ->assertSee('Claude Next 6');
});

it('switches to the new provider\'s most capable model when the provider changes', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->set('form.ai_provider', 'openrouter')
        ->assertSet('form.ai_model', 'anthropic/claude-fable-5.1')
        ->set('form.ai_provider', 'nonsense')
        ->assertSet('form.ai_model', 'anthropic/claude-fable-5.1')
        ->set('form.ai_provider', 'anthropic')
        ->assertSet('form.ai_model', 'claude-opus-5-5');
});

it('uses a model name typed into the model box', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->set('modelSearch', '  claude-custom-1  ')
        ->call('useTypedModel')
        ->assertSet('form.ai_model', 'claude-custom-1')
        ->assertSet('modelSearch', '')
        ->assertSee('claude-custom-1');
});

it('connects the AI Assistant through openrouter', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->set('form.ai_provider', 'openrouter')
        ->set('form.ai_api_key', 'sk-or-key')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('ai_provider'))->toBe('openrouter')
        ->and(Settings::get('ai_model'))->toBe('anthropic/claude-fable-5.1');

    new IntegrationServiceProvider(app())->applySavedSettings();

    expect(config('ai.default'))->toBe('openrouter')
        ->and(config('ai.providers.openrouter.key'))->toBe('sk-or-key');
});

it('connects the AI Assistant by persisting provider, key and model', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->set('form.ai_provider', 'anthropic')
        ->set('form.ai_api_key', '  sk-ant-new  ')
        ->set('form.ai_model', '  claude-opus-4-8  ')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('ai_provider'))->toBe('anthropic')
        ->and(Settings::get('ai_api_key'))->toBe('sk-ant-new')
        ->and(Settings::get('ai_model'))->toBe('claude-opus-4-8');
});

it('requires an api key and a known provider to connect the AI Assistant', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->set('form.ai_provider', 'cohere')
        ->set('form.ai_api_key', '')
        ->set('form.ai_model', 'x')
        ->call('connect')
        ->assertHasErrors(['form.ai_provider', 'form.ai_api_key']);
});

it('connects the AI Assistant with the gemini provider', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->set('form.ai_provider', 'gemini')
        ->set('form.ai_api_key', 'gemini-key')
        ->set('form.ai_model', 'gemini-model-name')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('ai_provider'))->toBe('gemini')
        ->and(Settings::get('ai_model'))->toBe('gemini-model-name');
});

it('disconnects the AI Assistant by clearing only the api key', function (): void {
    Settings::set(['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-ant-x', 'ai_model' => 'claude-opus-4-8']);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->call('disconnect')
        ->assertHasNoErrors()
        ->assertSet('form.ai_api_key', '');

    expect(Settings::get('ai_api_key'))->toBe('')
        ->and(Settings::get('ai_model'))->toBe('claude-opus-4-8');
});

it('tests the typed assistant credentials without saving them', function (): void {
    ConnectionCheck::fake(['OK']);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.assistant')
        ->set('form.ai_provider', 'anthropic')
        ->set('form.ai_api_key', 'sk-ant-typed')
        ->set('form.ai_model', '  claude-sonnet-5  ')
        ->call('test')
        ->assertHasNoErrors();

    ConnectionCheck::assertPrompted('ping');

    expect(Settings::get('ai_api_key'))->toBeNull();
});
