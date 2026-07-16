<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;

final class MailIntegrationForm extends Form
{
    public string $mail_provider = 'gmail';

    public string $mail_host = '';

    public int $mail_port = 587;

    public string $mail_username = '';

    public string $mail_password = '';

    public string $mail_encryption = 'tls';

    public string $mail_from_address = '';

    public string $mail_from_name = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mail_provider' => ['required', 'string', Rule::in(array_keys(config()->array('mail_providers')))],
            'mail_host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'mail_port' => ['required', 'integer', 'between:1,65535'],
            'mail_username' => ['required', 'string', 'max:255'],
            'mail_password' => ['required', 'string', 'max:500'],
            'mail_encryption' => ['required', 'string', 'in:tls,ssl'],
            'mail_from_address' => ['required', 'string', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mail_host.regex' => __('Enter a valid SMTP host, like smtp.example.com.'),
            'mail_port.between' => __('Enter a port between 1 and 65535.'),
            'mail_encryption.in' => __('Encryption must be TLS or SSL.'),
            'mail_from_address.email' => __('Enter a valid From email address.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'mail_host' => __('SMTP host'),
            'mail_port' => __('port'),
            'mail_username' => __('username'),
            'mail_password' => __('password'),
            'mail_from_address' => __('From address'),
            'mail_from_name' => __('From name'),
        ];
    }
}
