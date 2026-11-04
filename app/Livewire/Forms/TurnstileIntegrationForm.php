<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

final class TurnstileIntegrationForm extends Form
{
    public string $turnstile_site_key = '';

    public string $turnstile_secret_key = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'turnstile_site_key' => ['required', 'string', 'max:100', 'regex:/^[0-9A-Za-z_-]+$/'],
            'turnstile_secret_key' => [filled(config('site.turnstile_secret_key')) ? 'nullable' : 'required', 'string', 'max:100', 'regex:/^[0-9A-Za-z_-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'turnstile_site_key.regex' => __('Paste the site key exactly as Cloudflare shows it.'),
            'turnstile_secret_key.regex' => __('Paste the secret key exactly as Cloudflare shows it.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'turnstile_site_key' => __('site key'),
            'turnstile_secret_key' => __('secret key'),
        ];
    }
}
