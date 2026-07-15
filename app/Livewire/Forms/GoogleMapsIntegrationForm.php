<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

final class GoogleMapsIntegrationForm extends Form
{
    public string $google_maps_api_key = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'google_maps_api_key' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'google_maps_api_key' => __('Google Maps API key'),
        ];
    }
}
