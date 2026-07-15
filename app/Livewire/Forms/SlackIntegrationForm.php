<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

final class SlackIntegrationForm extends Form
{
    public string $slack_webhook_url = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'slack_webhook_url' => ['required', 'string', 'max:500', 'url', 'regex:#^https://hooks\.slack\.com/#'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slack_webhook_url.url' => __('Enter a valid Slack incoming webhook URL.'),
            'slack_webhook_url.regex' => __('Enter a valid Slack incoming webhook URL, like https://hooks.slack.com/services/…'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'slack_webhook_url' => __('Slack webhook URL'),
        ];
    }
}
