<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\Slack;
use App\Livewire\Forms\SlackIntegrationForm;
use App\Services\IntegrationTester;
use App\Traits\ConnectsIntegration;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public SlackIntegrationForm $form;

    public function connect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $this->form->slack_webhook_url = mb_trim($this->form->slack_webhook_url);

        $validated = $this->form->validate();

        $action->handle(['slack_webhook_url' => $this->typedOrSaved($validated['slack_webhook_url'], 'slack_webhook_url')]);

        $this->form->reset('slack_webhook_url');

        $this->finish(__('Slack connected.'));
    }

    public function test(IntegrationTester $tester): void
    {
        $this->authorize('settings.edit');

        $this->form->slack_webhook_url = mb_trim($this->form->slack_webhook_url);

        $validated = $this->form->validate();

        if (! $this->allowsAnotherTest()) {
            return;
        }

        $this->reportTest($tester->slack($this->typedOrSaved($validated['slack_webhook_url'], 'slack_webhook_url')));
    }

    protected function integration(): Slack
    {
        return new Slack;
    }
};
?>

<div>
    <x-admin.integration-card
        name="Slack"
        :description="__('Get a Slack message when someone submits a form.')"
        modal="integration-slack"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <svg viewBox="0 0 122.8 122.8" class="size-6" xmlns="http://www.w3.org/2000/svg">
                <path fill="#E01E5A" d="M25.8 77.6c0 7.1-5.8 12.9-12.9 12.9S0 84.7 0 77.6s5.8-12.9 12.9-12.9h12.9v12.9zm6.5 0c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9v32.3c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V77.6z" />
                <path fill="#36C5F0" d="M45.2 25.8c-7.1 0-12.9-5.8-12.9-12.9S38.1 0 45.2 0s12.9 5.8 12.9 12.9v12.9H45.2zm0 6.5c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H12.9C5.8 58.1 0 52.3 0 45.2s5.8-12.9 12.9-12.9h32.3z" />
                <path fill="#2EB67D" d="M97 45.2c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9-5.8 12.9-12.9 12.9H97V45.2zm-6.5 0c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V12.9C64.7 5.8 70.5 0 77.6 0s12.9 5.8 12.9 12.9v32.3z" />
                <path fill="#ECB22E" d="M77.6 97c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9-12.9-5.8-12.9-12.9V97h12.9zm0-6.5c-7.1 0-12.9-5.8-12.9-12.9s5.8-12.9 12.9-12.9h32.3c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H77.6z" />
            </svg>
        </x-slot:logo>
    </x-admin.integration-card>

    <flux:modal name="integration-slack" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect Slack') }}</flux:heading>
                @php($slackWebhooksLink = '<a href="https://api.slack.com/messaging/webhooks" target="_blank" rel="noopener noreferrer" class="underline">'.__('incoming webhook').'</a>')
                <flux:text class="mt-2">{!! __('Posts a message with the submitted details every time a form is filled in. Create an :webhook in your Slack workspace — you pick the channel there — and paste the URL.', ['webhook' => $slackWebhooksLink]) !!}</flux:text>
            </div>

            <flux:input
                wire:model="form.slack_webhook_url"
                type="password"
                viewable
                :label="__('Slack webhook URL')"
                :placeholder="$this->connected ? $this->hint('slack_webhook_url') : 'https://hooks.slack.com/services/…'"
                :description="$this->connected ? __('Leave blank to keep the saved webhook.') : null"
            />

            <x-admin.integration-actions
                :connected="$this->connected"
                :test="__('Posts a test message to your Slack channel')"
            />
        </form>
    </flux:modal>
</div>
