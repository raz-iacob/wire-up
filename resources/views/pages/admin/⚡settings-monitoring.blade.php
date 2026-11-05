<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Models\Role;
use App\Services\IntegrationTester;
use App\Services\IntegrationTestResult;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Sentry\Dsn;

return new class extends Component
{
    public string $sentry_dsn = '';

    public string $sentry_traces_sample_rate = '';

    public string $nightwatch_token = '';

    public string $nightwatch_ingest_uri = '';

    public string $nightwatch_request_sample_rate = '';

    public function mount(): void
    {
        $this->sentry_traces_sample_rate = $this->savedSetting('sentry_traces_sample_rate');
        $this->nightwatch_ingest_uri = $this->savedSetting('nightwatch_ingest_uri');
        $this->nightwatch_request_sample_rate = $this->savedSetting('nightwatch_request_sample_rate');
    }

    #[Computed]
    public function sentryDsnHint(): string
    {
        $saved = $this->savedSetting('sentry_dsn');

        return $saved === '' ? '' : 'https://…@'.Str::after($saved, '@');
    }

    #[Computed]
    public function nightwatchTokenHint(): string
    {
        $saved = $this->savedSetting('nightwatch_token');

        return $saved === '' ? '' : '…'.Str::substr($saved, -4);
    }

    #[Computed]
    public function agentCommand(): string
    {
        $address = $this->nightwatch_ingest_uri !== '' ? $this->nightwatch_ingest_uri : config()->string('nightwatch.ingest.uri');

        return 'php artisan nightwatch:agent --listen-on='.$address;
    }

    public function updateSentry(UpdateSettingsAction $action): void
    {
        $this->authorize(Role::DEVELOPER);

        $this->sentry_dsn = mb_trim($this->sentry_dsn);
        $this->sentry_traces_sample_rate = mb_trim($this->sentry_traces_sample_rate);

        $validated = $this->validate([
            'sentry_dsn' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    Dsn::createFromString((string) $value);
                } catch (InvalidArgumentException) {
                    $fail(__('Enter a valid Sentry DSN, like https://…@o0.ingest.sentry.io/0'));
                }
            }],
            'sentry_traces_sample_rate' => ['nullable', 'numeric', 'between:0,1'],
        ], [
            'sentry_traces_sample_rate.numeric' => __('Enter a number from 0 to 1.'),
            'sentry_traces_sample_rate.between' => __('Enter a number from 0 to 1.'),
        ], [
            'sentry_dsn' => __('DSN'),
        ]);

        $action->handle([
            'sentry_dsn' => $validated['sentry_dsn'] ?: $this->savedSetting('sentry_dsn'),
            'sentry_traces_sample_rate' => $this->sampleRate($validated['sentry_traces_sample_rate']),
        ]);

        $this->reset('sentry_dsn');

        Flux::toast(__('Sentry settings have been updated.'), variant: 'success');
    }

    public function removeSentryDsn(UpdateSettingsAction $action): void
    {
        $this->authorize(Role::DEVELOPER);

        $action->handle(['sentry_dsn' => '']);

        $this->reset('sentry_dsn');

        Flux::toast(__('Sentry DSN removed.'), variant: 'success');
    }

    public function testSentry(IntegrationTester $tester): void
    {
        $this->authorize(Role::DEVELOPER);

        if (blank(config('sentry.dsn'))) {
            Flux::toast(__('Save a Sentry DSN first.'), variant: 'warning');

            return;
        }

        if (! $this->tooManyTests()) {
            $this->showResult($tester->sentry());
        }
    }

    public function updateNightwatch(UpdateSettingsAction $action): void
    {
        $this->authorize(Role::DEVELOPER);

        $this->nightwatch_token = mb_trim($this->nightwatch_token);
        $this->nightwatch_ingest_uri = mb_trim($this->nightwatch_ingest_uri);
        $this->nightwatch_request_sample_rate = mb_trim($this->nightwatch_request_sample_rate);

        $validated = $this->validate([
            'nightwatch_token' => ['nullable', 'string', 'max:500', 'regex:/^\S+$/'],
            'nightwatch_ingest_uri' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+:\d{1,5}$/'],
            'nightwatch_request_sample_rate' => ['nullable', 'numeric', 'between:0,1'],
        ], [
            'nightwatch_token.regex' => __('Enter the token without spaces.'),
            'nightwatch_ingest_uri.regex' => __('Enter an address like 127.0.0.1:2407.'),
            'nightwatch_request_sample_rate.numeric' => __('Enter a number from 0 to 1.'),
            'nightwatch_request_sample_rate.between' => __('Enter a number from 0 to 1.'),
        ], [
            'nightwatch_token' => __('token'),
            'nightwatch_ingest_uri' => __('agent address'),
        ]);

        $action->handle([
            'nightwatch_token' => $validated['nightwatch_token'] ?: $this->savedSetting('nightwatch_token'),
            'nightwatch_ingest_uri' => (string) $validated['nightwatch_ingest_uri'],
            'nightwatch_request_sample_rate' => $this->sampleRate($validated['nightwatch_request_sample_rate']),
        ]);

        $this->reset('nightwatch_token');

        Flux::toast(__('Nightwatch settings have been updated.'), variant: 'success');
    }

    public function removeNightwatchToken(UpdateSettingsAction $action): void
    {
        $this->authorize(Role::DEVELOPER);

        $action->handle(['nightwatch_token' => '']);

        $this->reset('nightwatch_token');

        Flux::toast(__('Nightwatch token removed.'), variant: 'success');
    }

    public function checkNightwatch(IntegrationTester $tester): void
    {
        $this->authorize(Role::DEVELOPER);

        if (blank(config('nightwatch.token'))) {
            Flux::toast(__('Save a Nightwatch token first.'), variant: 'warning');

            return;
        }

        if (! $this->tooManyTests()) {
            $this->showResult($tester->nightwatch());
        }
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Monitoring'))
            ->layout('layouts::admin');
    }

    private function tooManyTests(): bool
    {
        $key = 'monitoring-test:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            Flux::toast(__('That’s a lot of tests. Please wait a moment and try again.'), variant: 'warning');

            return true;
        }

        RateLimiter::hit($key, decaySeconds: 60);

        return false;
    }

    private function showResult(IntegrationTestResult $result): void
    {
        Flux::toast($result->message, variant: $result->passed ? 'success' : 'danger');
    }

    private function sampleRate(mixed $value): float|string
    {
        return is_numeric($value) ? (float) $value : '';
    }

    private function savedSetting(string $key): string
    {
        $value = config('site.'.$key);

        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }
};
?>

<x-admin.settings-layout>
    <div class="max-w-xl space-y-12">
        <form
            wire:submit="updateSentry"
            wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
            class="space-y-6"
            data-sentry
        >
            <div>
                <flux:heading size="lg">{{ __('Sentry') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Send this site’s errors to Sentry.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('DSN') }}</flux:label>
                @if ($this->sentryDsnHint !== '')
                    <flux:description>{{ __('Leave blank to keep the saved DSN.') }}</flux:description>
                @endif
                <flux:input.group>
                    <flux:input
                        wire:model="sentry_dsn"
                        type="password"
                        viewable
                        :placeholder="$this->sentryDsnHint !== '' ? $this->sentryDsnHint : 'https://…@o0.ingest.sentry.io/0'"
                    />
                    @if ($this->sentryDsnHint !== '')
                        <flux:button wire:click="removeSentryDsn">{{ __('Remove') }}</flux:button>
                    @endif
                </flux:input.group>
                <flux:error name="sentry_dsn" />
            </flux:field>

            <flux:input
                wire:model="sentry_traces_sample_rate"
                type="number"
                step="any"
                min="0"
                max="1"
                :label="__('Traces sample rate')"
                :description="__('Share of requests to trace, from 0 to 1. Leave blank to send errors only.')"
            />

            <div class="flex items-center gap-2">
                <flux:button type="submit" variant="primary" icon="check">{{ __('Update') }}</flux:button>
                <flux:button wire:click="testSentry">{{ __('Send a test event') }}</flux:button>
            </div>
        </form>

        <flux:separator variant="subtle" />

        <form
            wire:submit="updateNightwatch"
            wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
            class="space-y-6"
            data-nightwatch
        >
            <div>
                <flux:heading size="lg">{{ __('Nightwatch') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Send requests, jobs, queries and errors to Laravel Nightwatch.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Token') }}</flux:label>
                @if ($this->nightwatchTokenHint !== '')
                    <flux:description>{{ __('Leave blank to keep the saved token.') }}</flux:description>
                @endif
                <flux:input.group>
                    <flux:input
                        wire:model="nightwatch_token"
                        type="password"
                        viewable
                        :placeholder="$this->nightwatchTokenHint"
                    />
                    @if ($this->nightwatchTokenHint !== '')
                        <flux:button wire:click="removeNightwatchToken">{{ __('Remove') }}</flux:button>
                    @endif
                </flux:input.group>
                <flux:error name="nightwatch_token" />
            </flux:field>

            <flux:input
                wire:model="nightwatch_ingest_uri"
                :label="__('Agent address')"
                :description="__('Give each site on a server its own port.')"
                placeholder="127.0.0.1:2407"
            />

            <flux:input
                wire:model="nightwatch_request_sample_rate"
                type="number"
                step="any"
                min="0"
                max="1"
                :label="__('Request sample rate')"
                :description="__('Share of requests to record, from 0 to 1.')"
                placeholder="1"
            />

            <flux:input
                :value="$this->agentCommand"
                readonly
                copyable
                class="font-mono text-sm"
                :label="__('Agent command')"
                :description="__('Keep this running on the server, and restart it after changing the token.')"
            />

            <div class="flex items-center gap-2">
                <flux:button type="submit" variant="primary" icon="check">{{ __('Update') }}</flux:button>
                <flux:button wire:click="checkNightwatch">{{ __('Check the agent') }}</flux:button>
            </div>
        </form>
    </div>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('Monitoring') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Monitoring') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
