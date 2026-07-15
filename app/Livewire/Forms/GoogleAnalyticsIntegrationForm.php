<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Closure;
use Livewire\Form;

final class GoogleAnalyticsIntegrationForm extends Form
{
    public string $google_analytics_id = '';

    public string $google_analytics_property_id = '';

    public string $google_analytics_credentials = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'google_analytics_id' => ['required', 'string', 'max:40', 'regex:/^G-[A-Z0-9]+$/'],
            'google_analytics_property_id' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/', 'required_with:google_analytics_credentials'],
            'google_analytics_credentials' => ['nullable', 'string', 'max:10000', 'required_with:google_analytics_property_id', function (string $attribute, mixed $value, Closure $fail): void {
                $decoded = json_decode((string) $value, true);
                $email = is_array($decoded) ? ($decoded['client_email'] ?? null) : null;
                $key = is_array($decoded) ? ($decoded['private_key'] ?? null) : null;

                if (! is_string($email) || $email === '' || ! is_string($key) || $key === '') {
                    $fail(__('Paste the full service account JSON key file.'));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'google_analytics_id.regex' => __('Enter a valid Google Analytics measurement ID, like G-XXXXXXXXXX.'),
            'google_analytics_property_id.regex' => __('Enter the numeric GA4 property ID, like 123456789.'),
            'google_analytics_property_id.required_with' => __('Add the property ID to enable analytics reports.'),
            'google_analytics_credentials.required_with' => __('Add the service account JSON to enable analytics reports.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'google_analytics_id' => __('Google Analytics measurement ID'),
            'google_analytics_property_id' => __('GA4 property ID'),
            'google_analytics_credentials' => __('service account JSON'),
        ];
    }
}
