<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

final class PexelsIntegrationForm extends Form
{
    public string $pexels_api_key = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'pexels_api_key' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'pexels_api_key' => __('Pexels API key'),
        ];
    }
}
