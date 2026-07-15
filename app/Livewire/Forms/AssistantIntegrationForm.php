<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

final class AssistantIntegrationForm extends Form
{
    public string $ai_provider = 'anthropic';

    public string $ai_api_key = '';

    public string $ai_model = 'claude-opus-4-8';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ai_provider' => ['required', 'string', 'in:anthropic,openai,gemini'],
            'ai_api_key' => ['required', 'string', 'max:255'],
            'ai_model' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'ai_api_key' => __('API key'),
            'ai_model' => __('model'),
        ];
    }
}
