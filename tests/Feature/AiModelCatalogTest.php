<?php

declare(strict_types=1);

use App\Services\AiModelCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('lists the newest anthropic models and caches them per key', function (): void {
    Http::fake(['api.anthropic.com/v1/models*' => Http::response(['data' => [
        ['id' => 'claude-next-6', 'display_name' => 'Claude Next 6'],
        ['id' => 'claude-unnamed'],
        ['display_name' => 'No id'],
        'junk',
    ]])]);

    $catalog = resolve(AiModelCatalog::class);

    expect($catalog->options('anthropic', 'sk-ant-live'))->toBe(['claude-next-6' => 'Claude Next 6', 'claude-unnamed' => 'claude-unnamed'])
        ->and($catalog->options('anthropic', 'sk-ant-live'))->toHaveKey('claude-next-6');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'sk-ant-live')
        && $request['limit'] === AiModelCatalog::LIMIT);
});

it('lists the newest openai chat models, leaving out snapshots and other kinds of model', function (): void {
    Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [
        ['id' => 'gpt-old', 'created' => 100],
        ['id' => 'gpt-new', 'created' => 300],
        ['id' => 'o9', 'created' => 200],
        ['id' => 'gpt-new-2026-01-01', 'created' => 400],
        ['id' => 'gpt-new-realtime', 'created' => 500],
        ['id' => 'text-embedding-9', 'created' => 600],
        ['created' => 700],
    ]])]);

    expect(resolve(AiModelCatalog::class)->options('openai', 'sk-openai'))->toBe(['gpt-new' => 'gpt-new', 'o9' => 'o9', 'gpt-old' => 'gpt-old']);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk-openai'));
});

it('lists the newest gemini chat models by version', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['models' => [
        ['name' => 'models/gemini-2.5-pro', 'displayName' => 'Gemini 2.5 Pro', 'supportedGenerationMethods' => ['generateContent']],
        ['name' => 'models/gemini-3.8-flash', 'displayName' => 'Gemini 3.8 Flash', 'supportedGenerationMethods' => ['generateContent']],
        ['name' => 'models/gemini-3-pro', 'supportedGenerationMethods' => ['generateContent']],
        ['name' => 'models/gemini-3.8-flash-tts', 'supportedGenerationMethods' => ['generateContent']],
        ['name' => 'models/gemini-embedding-2', 'supportedGenerationMethods' => ['embedContent']],
        ['name' => 'models/gemma-4', 'supportedGenerationMethods' => ['generateContent']],
    ]])]);

    expect(resolve(AiModelCatalog::class)->options('gemini', 'gemini-key'))->toBe([
        'gemini-3.8-flash' => 'Gemini 3.8 Flash',
        'gemini-3-pro' => 'gemini-3-pro',
        'gemini-2.5-pro' => 'Gemini 2.5 Pro',
    ]);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'gemini-key'));
});

it('lists the two newest tool-using text models from each major lab on openrouter', function (): void {
    $model = fn (string $id, int $created, array $extra = []): array => [
        'id' => $id, 'name' => $id.' name', 'created' => $created, 'supported_parameters' => ['tools'], ...$extra,
    ];

    Http::fake(['openrouter.ai/api/v1/models' => Http::response(['data' => [
        $model('anthropic/claude-a', 1),
        $model('anthropic/claude-b', 3),
        $model('anthropic/claude-c', 2),
        $model('anthropic/claude-c:free', 9),
        $model('openai/gpt-x', 5, ['name' => null]),
        $model('openai/gpt-image', 6, ['architecture' => ['output_modalities' => ['image', 'text']]]),
        $model('google/gemini-y', 4, ['supported_parameters' => []]),
        $model('meta/llama', 8),
        $model('no-lab', 8),
    ]])]);

    expect(resolve(AiModelCatalog::class)->options('openrouter', 'sk-or'))->toBe([
        'anthropic/claude-b' => 'anthropic/claude-b name',
        'anthropic/claude-c' => 'anthropic/claude-c name',
        'openai/gpt-x' => 'openai/gpt-x',
    ]);
});

it('falls back to the sdk models without a key, on failure or on an empty list', function (array $response, int $status): void {
    Http::fake(['*' => Http::response($response, $status)]);

    $catalog = resolve(AiModelCatalog::class);

    expect($catalog->options('anthropic', ''))->toBe([
        'claude-opus-5-5' => 'claude-opus-5-5 — most capable',
        'claude-sonnet-5-5' => 'claude-sonnet-5-5 — balanced',
        'claude-haiku-5-5' => 'claude-haiku-5-5 — fastest',
    ])
        ->and($catalog->options('gemini', 'bad-key'))->toBe($catalog->fallback('gemini'))
        ->and($catalog->fallback('gemini'))->toHaveCount(2);
})->with([
    'rejected key' => [['error' => ['type' => 'authentication_error']], 401],
    'empty list' => [['data' => [], 'models' => []], 200],
    'no list' => [[], 200],
]);

it('defaults each provider to its most capable model', function (): void {
    expect(resolve(AiModelCatalog::class)->defaultFor('anthropic'))->toBe('claude-opus-5-5')
        ->and(resolve(AiModelCatalog::class)->defaultFor('openrouter'))->toBe('anthropic/claude-fable-5.1');
});

it('keeps the saved model selectable when it is no longer listed', function (): void {
    expect(resolve(AiModelCatalog::class)->options('anthropic', '', 'claude-opus-4-8'))
        ->toHaveKey('claude-opus-4-8', 'claude-opus-4-8')
        ->toHaveKey('claude-opus-5-5');
});
