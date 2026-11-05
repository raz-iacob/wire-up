<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Services\NewsletterService;
use Livewire\Form;

final class NewsletterIntegrationForm extends Form
{
    public string $newsletter_provider = 'mailchimp';

    public string $newsletter_api_key = '';

    public string $newsletter_list_id = '';

    public bool $newsletter_double_opt_in = true;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'newsletter_provider' => ['required', 'string', 'in:'.implode(',', array_keys(NewsletterService::PROVIDERS))],
            'newsletter_api_key' => [
                $this->keepsSavedKey() ? 'nullable' : 'required',
                'string',
                'max:200',
                $this->newsletter_provider === 'mailchimp' ? 'regex:/^[0-9a-f]{32}-[a-z]{2,4}\d{1,3}$/' : 'regex:/^\S+$/',
            ],
            'newsletter_list_id' => [$this->usesList() ? 'required' : 'nullable', 'string', 'max:50', 'regex:/^[0-9A-Za-z]+$/'],
            'newsletter_double_opt_in' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'newsletter_api_key.regex' => $this->newsletter_provider === 'mailchimp'
                ? __('Paste the whole Mailchimp API key, including the part after the dash.')
                : __('Paste the API key exactly as :provider shows it.', ['provider' => $this->providerName()]),
            'newsletter_list_id.regex' => __('Paste the ID exactly as :provider shows it.', ['provider' => $this->providerName()]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'newsletter_provider' => __('service'),
            'newsletter_api_key' => __('API key'),
            'newsletter_list_id' => $this->newsletter_provider === 'kit' ? __('form ID') : __('audience ID'),
        ];
    }

    public function usesList(): bool
    {
        return $this->newsletter_provider !== 'buttondown';
    }

    public function offersDoubleOptIn(): bool
    {
        return in_array($this->newsletter_provider, NewsletterService::DOUBLE_OPT_IN_PROVIDERS, true);
    }

    public function apiKeyHelp(): string
    {
        return match ($this->newsletter_provider) {
            'mailchimp' => __('In Mailchimp under Profile → Extras → API keys.'),
            'kit' => __('A V4 key, in Kit under Settings → Developer.'),
            default => __('In Buttondown under Settings → API.'),
        };
    }

    public function listHelp(): string
    {
        return $this->newsletter_provider === 'kit'
            ? __('The number in the form’s address in Kit.')
            : __('In Mailchimp under Audience → Settings → Audience name and defaults.');
    }

    public function keepsSavedKey(): bool
    {
        return filled(config('site.newsletter_api_key')) && config('site.newsletter_provider') === $this->newsletter_provider;
    }

    private function providerName(): string
    {
        return NewsletterService::PROVIDERS[$this->newsletter_provider] ?? '';
    }
}
