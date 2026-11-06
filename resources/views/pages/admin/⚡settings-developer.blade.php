<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\ErrorReports;
use App\Models\Role;
use App\Services\ErrorReporter;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public string $error_report_email = '';

    public string $error_report_slack_webhook_url = '';

    public function mount(): void
    {
        $this->error_report_email = $this->savedSetting('error_report_email');
    }

    #[Computed]
    public function slackWebhookHint(): string
    {
        return new ErrorReports()->hint('error_report_slack_webhook_url', $this->savedSetting('error_report_slack_webhook_url'));
    }

    public function update(UpdateSettingsAction $action): void
    {
        $this->authorize(Role::DEVELOPER);

        $this->error_report_email = mb_trim($this->error_report_email);
        $this->error_report_slack_webhook_url = mb_trim($this->error_report_slack_webhook_url);

        $validated = $this->validate([
            'error_report_email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'error_report_slack_webhook_url' => ['nullable', 'string', 'max:500', 'url', 'regex:#^https://hooks\.slack\.com/#'],
        ], [
            'error_report_email.email' => __('Enter a valid email address.'),
            'error_report_slack_webhook_url.url' => __('Enter a valid Slack incoming webhook URL.'),
            'error_report_slack_webhook_url.regex' => __('Enter a valid Slack incoming webhook URL, like https://hooks.slack.com/services/…'),
        ]);

        $action->handle([
            'error_report_email' => (string) $validated['error_report_email'],
            'error_report_slack_webhook_url' => $validated['error_report_slack_webhook_url'] ?: $this->savedSetting('error_report_slack_webhook_url'),
        ]);

        $this->reset('error_report_slack_webhook_url');

        Flux::toast(__('Error reports have been updated.'), variant: 'success');
    }

    public function removeSlackWebhook(UpdateSettingsAction $action): void
    {
        $this->authorize(Role::DEVELOPER);

        $action->handle(['error_report_slack_webhook_url' => '']);

        $this->reset('error_report_slack_webhook_url');

        Flux::toast(__('Slack webhook removed.'), variant: 'success');
    }

    public function sendTest(ErrorReporter $reporter): void
    {
        $this->authorize(Role::DEVELOPER);

        if ($reporter->routes() === []) {
            Flux::toast(__('Save an email address or a Slack webhook first.'), variant: 'warning');

            return;
        }

        $key = 'error-report-test:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            Flux::toast(__('That’s a lot of tests. Please wait a moment and try again.'), variant: 'warning');

            return;
        }

        RateLimiter::hit($key, decaySeconds: 60);

        $failures = $reporter->sendTest();

        Flux::toast(
            $failures === [] ? __('Test report sent.') : __('The test report could not be sent: :reason', ['reason' => Str::limit(implode(' ', $failures), 200)]),
            variant: $failures === [] ? 'success' : 'danger',
        );
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Developer'))
            ->layout('layouts::admin');
    }

    private function savedSetting(string $key): string
    {
        $value = config('site.'.$key);

        return is_string($value) ? $value : '';
    }
};
?>

<x-admin.settings-layout>
    <form
        wire:submit="update"
        wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="max-w-xl space-y-6"
    >
        <div>
            <flux:heading size="lg">{{ __('Error reports') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Get an email or a Slack message when something goes wrong on this site. Each error is sent at most once an hour.') }}</flux:text>
        </div>

        <flux:input
            wire:model="error_report_email"
            type="email"
            :label="__('Email')"
            :placeholder="auth()->user()?->email"
        />

        <flux:field>
            <flux:label>{{ __('Slack webhook URL') }}</flux:label>
            @if ($this->slackWebhookHint !== '')
                <flux:description>{{ __('Leave blank to keep the saved webhook.') }}</flux:description>
            @endif
            <flux:input.group>
                <flux:input
                    wire:model="error_report_slack_webhook_url"
                    type="password"
                    viewable
                    :placeholder="$this->slackWebhookHint !== '' ? $this->slackWebhookHint : 'https://hooks.slack.com/services/…'"
                />
                @if ($this->slackWebhookHint !== '')
                    <flux:button wire:click="removeSlackWebhook">{{ __('Remove') }}</flux:button>
                @endif
            </flux:input.group>
            <flux:error name="error_report_slack_webhook_url" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" icon="check">{{ __('Update') }}</flux:button>
            <flux:button wire:click="sendTest">{{ __('Send a test report') }}</flux:button>
        </div>
    </form>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('Developer') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Developer') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
