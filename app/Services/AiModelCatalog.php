<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;
use Throwable;

final class AiModelCatalog
{
    public const array PROVIDERS = [
        'anthropic' => 'Claude (Anthropic)',
        'openai' => 'OpenAI',
        'gemini' => 'Gemini (Google)',
        'openrouter' => 'OpenRouter',
    ];

    public const int LIMIT = 8;

    public const array OPENROUTER_LABS = ['anthropic', 'openai', 'google', 'x-ai'];

    /**
     * @return array<string, string>
     */
    public function options(string $provider, string $apiKey, string $current = ''): array
    {
        $models = $apiKey === '' ? $this->fallback($provider) : $this->latest($provider, $apiKey);

        if ($current !== '' && ! array_key_exists($current, $models)) {
            $models[$current] = $current;
        }

        return $models;
    }

    public function defaultFor(string $provider): string
    {
        return (string) array_key_first($this->fallback($provider));
    }

    /**
     * @return array<string, string>
     */
    public function fallback(string $provider): array
    {
        if (! array_key_exists($provider, self::PROVIDERS)) {
            return [];
        }

        $sdk = Ai::textProvider($provider);

        $models = [];

        foreach ([
            $sdk->smartestTextModel() => __('most capable'),
            $sdk->defaultTextModel() => __('balanced'),
            $sdk->cheapestTextModel() => __('fastest'),
        ] as $model => $role) {
            $models[$model] ??= $model.' — '.$role;
        }

        return $models;
    }

    /**
     * @return array<string, string>
     */
    private function latest(string $provider, string $apiKey): array
    {
        $key = 'ai-models:'.$provider.':'.hash('sha256', $apiKey);

        /** @var array<string, string>|null $cached */
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $models = match ($provider) {
                'anthropic' => $this->anthropic($apiKey),
                'openai' => $this->openai($apiKey),
                'gemini' => $this->gemini($apiKey),
                default => $this->openrouter($apiKey),
            };
        } catch (Throwable) {
            return $this->fallback($provider);
        }

        if ($models === []) {
            return $this->fallback($provider);
        }

        Cache::put($key, $models, now()->addDay());

        return $models;
    }

    /**
     * @return array<string, string>
     */
    private function anthropic(string $apiKey): array
    {
        $models = [];

        foreach ($this->fetch(
            Http::withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01']),
            'https://api.anthropic.com/v1/models',
            ['limit' => self::LIMIT],
            'data',
        ) as $model) {
            if (is_string($model['id'] ?? null)) {
                $models[$model['id']] = is_string($model['display_name'] ?? null) ? $model['display_name'] : $model['id'];
            }
        }

        return $models;
    }

    /**
     * @return array<string, string>
     */
    private function openai(string $apiKey): array
    {
        $chatModels = array_filter(
            $this->fetch(Http::withToken($apiKey), 'https://api.openai.com/v1/models', [], 'data'),
            fn (array $model): bool => is_string($model['id'] ?? null)
                && preg_match('/^(gpt-|o\d|chatgpt-)/', $model['id']) === 1
                && preg_match('/audio|realtime|tts|transcribe|image|search|instruct|embedding|moderation|codex|computer-use|oss|-\d{4}(-\d{2}-\d{2})?$/', $model['id']) === 0,
        );

        usort($chatModels, fn (array $a, array $b): int => (int) ($b['created'] ?? 0) <=> (int) ($a['created'] ?? 0));

        return $this->keyById(array_slice($chatModels, 0, self::LIMIT), 'id', 'id');
    }

    /**
     * @return array<string, string>
     */
    private function gemini(string $apiKey): array
    {
        $chatModels = [];

        foreach ($this->fetch(
            Http::withHeaders(['x-goog-api-key' => $apiKey]),
            'https://generativelanguage.googleapis.com/v1beta/models',
            ['pageSize' => 1000],
            'models',
        ) as $model) {
            $id = mb_substr((string) ($model['name'] ?? ''), mb_strlen('models/'));

            if (str_starts_with($id, 'gemini-')
                && in_array('generateContent', (array) ($model['supportedGenerationMethods'] ?? []), true)
                && preg_match('/tts|image|embedding|live|audio|robotics|computer-use|-\d{2}-\d{2}$|-\d{3}$/', $id) === 0) {
                $chatModels[] = ['id' => $id, 'label' => is_string($model['displayName'] ?? null) ? $model['displayName'] : $id, 'version' => (float) mb_substr($id, 7)];
            }
        }

        usort($chatModels, fn (array $a, array $b): int => $b['version'] <=> $a['version']);

        return $this->keyById(array_slice($chatModels, 0, self::LIMIT), 'id', 'label');
    }

    /**
     * @return array<string, string>
     */
    private function openrouter(string $apiKey): array
    {
        $byLab = array_fill_keys(self::OPENROUTER_LABS, []);

        foreach ($this->fetch(Http::withToken($apiKey), 'https://openrouter.ai/api/v1/models', [], 'data') as $model) {
            $id = (string) ($model['id'] ?? '');
            $lab = mb_strstr($id, '/', true);

            if (is_string($lab) && isset($byLab[$lab]) && ! str_contains($id, ':')
                && in_array('tools', (array) ($model['supported_parameters'] ?? []), true)
                && (array) ($model['architecture']['output_modalities'] ?? ['text']) === ['text']) {
                $byLab[$lab][] = ['id' => $id, 'label' => is_string($model['name'] ?? null) ? $model['name'] : $id, 'created' => (int) ($model['created'] ?? 0)];
            }
        }

        $models = [];

        foreach ($byLab as $labModels) {
            usort($labModels, fn (array $a, array $b): int => $b['created'] <=> $a['created']);
            $models += $this->keyById(array_slice($labModels, 0, (int) (self::LIMIT / count(self::OPENROUTER_LABS))), 'id', 'label');
        }

        return $models;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function fetch(PendingRequest $request, string $url, array $query, string $listKey): array
    {
        $list = $request->timeout(5)->get($url, $query)->throw()->json($listKey);

        return array_values(array_filter(is_array($list) ? $list : [], is_array(...)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $models
     * @return array<string, string>
     */
    private function keyById(array $models, string $idKey, string $labelKey): array
    {
        $keyed = [];

        foreach ($models as $model) {
            $keyed[(string) $model[$idKey]] = (string) $model[$labelKey];
        }

        return $keyed;
    }
}
