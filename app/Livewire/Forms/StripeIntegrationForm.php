<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

final class StripeIntegrationForm extends Form
{
    public string $stripe_publishable_key = '';

    public string $stripe_secret_key = '';

    public string $stripe_webhook_secret = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'stripe_publishable_key' => ['required', 'string', 'max:255', 'regex:/^pk_(test|live)_/'],
            'stripe_secret_key' => ['nullable', 'string', 'max:255', 'regex:/^(sk|rk)_(test|live)_/'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255', 'regex:/^whsec_/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stripe_publishable_key.regex' => __('The publishable key starts with pk_test_ or pk_live_.'),
            'stripe_secret_key.regex' => __('The secret key starts with sk_ or rk_, followed by test_ or live_.'),
            'stripe_webhook_secret.regex' => __('The signing secret starts with whsec_.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'stripe_publishable_key' => __('Publishable key'),
            'stripe_secret_key' => __('Secret key'),
            'stripe_webhook_secret' => __('Webhook signing secret'),
        ];
    }
}
