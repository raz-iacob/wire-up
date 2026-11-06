<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Models\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

function fakeSentryTransport(bool $accepts = true): TransportInterface
{
    $transport = new class($accepts) implements TransportInterface
    {
        /** @var list<Event> */
        public array $events = [];

        public function __construct(private readonly bool $accepts) {}

        public function send(Event $event): Result
        {
            $this->events[] = $event;

            return $this->accepts ? new Result(ResultStatus::success(), $event) : new Result(ResultStatus::failed());
        }

        public function close(?int $timeout = null): Result
        {
            return new Result(ResultStatus::success());
        }
    };

    config()->set('sentry.dsn', 'https://public@o1.ingest.sentry.io/1');
    app()->instance(HubInterface::class, new Hub(ClientBuilder::create(['dsn' => 'https://public@o1.ingest.sentry.io/1'])->setTransport($transport)->getClient()));

    return $transport;
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->developer()->create(['active' => true]));
});

it('opens only for developers, as a tab next to error reports', function (): void {
    $this->actingAs(User::factory()->owner()->create(['active' => true]));

    $this->get(route('admin.settings-monitoring'))->assertForbidden();

    $this->actingAs(User::factory()->developer()->create(['active' => true]));

    $this->get(route('admin.settings-developer'))->assertOk()->assertSee(route('admin.settings-monitoring'));
    $this->get(route('admin.settings-monitoring'))
        ->assertOk()
        ->assertSee(['Sentry', 'Nightwatch', 'php artisan nightwatch:agent --listen-on=127.0.0.1:2407']);
});

it('saves the Sentry DSN and traces sample rate and never sends the saved DSN back to the browser', function (): void {
    Livewire::test('pages::admin.settings-monitoring')
        ->set('sentry_dsn', '  https://secretkey42@o42.ingest.sentry.io/77  ')
        ->set('sentry_traces_sample_rate', ' 0.25 ')
        ->call('updateSentry')
        ->assertHasNoErrors()
        ->assertSet('sentry_dsn', '')
        ->assertDispatched('toast-show', toastSays('Sentry settings have been updated.'));

    expect(Settings::get('sentry_dsn'))->toBe('https://secretkey42@o42.ingest.sentry.io/77')
        ->and(Settings::get('sentry_traces_sample_rate'))->toBe(0.25);

    $this->get(route('admin.settings-monitoring'))
        ->assertDontSee('secretkey42')
        ->assertSee(['https://…@o42.ingest.sentry.io/77', 'Leave blank to keep the saved DSN.']);
});

it('keeps the saved DSN when its field is left blank, clears the sample rate, and removes the DSN on request', function (): void {
    Settings::set(['sentry_dsn' => 'https://saved@o1.ingest.sentry.io/1', 'sentry_traces_sample_rate' => 0.5]);

    $component = Livewire::test('pages::admin.settings-monitoring')
        ->assertSet('sentry_traces_sample_rate', '0.5')
        ->set('sentry_traces_sample_rate', '')
        ->call('updateSentry')
        ->assertHasNoErrors();

    expect(Settings::get('sentry_dsn'))->toBe('https://saved@o1.ingest.sentry.io/1')
        ->and(Settings::get('sentry_traces_sample_rate'))->toBe('');

    $component->call('removeSentryDsn')->assertDispatched('toast-show', toastSays('Sentry DSN removed.'));

    expect(Settings::get('sentry_dsn'))->toBe('');
});

it('saves the Nightwatch token, agent address and sample rate, and never sends the saved token back', function (): void {
    Livewire::test('pages::admin.settings-monitoring')
        ->set('nightwatch_token', ' nw-secret-token-WXYZ ')
        ->set('nightwatch_ingest_uri', ' 127.0.0.1:2410 ')
        ->set('nightwatch_request_sample_rate', '0.1')
        ->call('updateNightwatch')
        ->assertHasNoErrors()
        ->assertSet('nightwatch_token', '')
        ->assertDispatched('toast-show', toastSays('Nightwatch settings have been updated.'));

    expect(Settings::get('nightwatch_token'))->toBe('nw-secret-token-WXYZ')
        ->and(Settings::get('nightwatch_ingest_uri'))->toBe('127.0.0.1:2410')
        ->and(Settings::get('nightwatch_request_sample_rate'))->toBe(0.1);

    $this->get(route('admin.settings-monitoring'))
        ->assertDontSee('nw-secret-token')
        ->assertSee(['…WXYZ', 'Leave blank to keep the saved token.', 'php artisan nightwatch:agent --listen-on=127.0.0.1:2410']);
});

it('keeps the saved Nightwatch token when its field is left blank, and removes it on request', function (): void {
    Settings::set(['nightwatch_token' => 'saved-token']);

    $component = Livewire::test('pages::admin.settings-monitoring')
        ->call('updateNightwatch')
        ->assertHasNoErrors();

    expect(Settings::get('nightwatch_token'))->toBe('saved-token');

    $component->call('removeNightwatchToken')->assertDispatched('toast-show', toastSays('Nightwatch token removed.'));

    expect(Settings::get('nightwatch_token'))->toBe('');
});

it('rejects a malformed DSN, token, agent address or sample rate with a friendly message', function (string $field, string $value, string $action, string $message): void {
    Livewire::test('pages::admin.settings-monitoring')
        ->set($field, $value)
        ->call($action)
        ->assertHasErrors([$field])
        ->assertSee($message);
})->with([
    'dsn' => ['sentry_dsn', 'not a dsn', 'updateSentry', 'Enter a valid Sentry DSN, like https://…@o0.ingest.sentry.io/0'],
    'traces rate' => ['sentry_traces_sample_rate', '1.5', 'updateSentry', 'Enter a number from 0 to 1.'],
    'token' => ['nightwatch_token', 'two words', 'updateNightwatch', 'Enter the token without spaces.'],
    'address' => ['nightwatch_ingest_uri', 'localhost', 'updateNightwatch', 'Enter an address like 127.0.0.1:2407.'],
    'request rate' => ['nightwatch_request_sample_rate', 'all', 'updateNightwatch', 'Enter a number from 0 to 1.'],
]);

it('sends a Sentry test event and shows its ID', function (): void {
    $transport = fakeSentryTransport();

    Livewire::test('pages::admin.settings-monitoring')
        ->call('testSentry')
        ->assertDispatched('toast-show', fn (string $event, array $payload): bool => ($payload['slots']['text'] ?? '') === 'Test event sent to Sentry with the ID '.$transport->events[0]->getId().'.');

    expect($transport->events[0]->getExceptions()[0]->getValue())->toContain('Sentry is receiving its errors.');
});

it('says when Sentry does not take the test event', function (): void {
    fakeSentryTransport(accepts: false);

    Livewire::test('pages::admin.settings-monitoring')
        ->call('testSentry')
        ->assertDispatched('toast-show', toastSays('Sentry didn’t take the test event. Check the DSN and try again.'));
});

it('asks for a DSN before sending a test event, and a token before checking the agent', function (): void {
    Process::fake();

    Livewire::test('pages::admin.settings-monitoring')
        ->call('testSentry')
        ->assertDispatched('toast-show', toastSays('Save a Sentry DSN first.'))
        ->call('checkNightwatch')
        ->assertDispatched('toast-show', toastSays('Save a Nightwatch token first.'));

    Process::assertNothingRan();
});

it('checks the Nightwatch agent with its status command', function (): void {
    config()->set('nightwatch.token', 'nw-token');
    Process::fake(['*' => Process::result(output: '   INFO  The Nightwatch agent is running and accepting connections.')]);

    Livewire::test('pages::admin.settings-monitoring')
        ->call('checkNightwatch')
        ->assertDispatched('toast-show', toastSays('The Nightwatch agent is running and accepting connections.'));

    Process::assertRan(fn (PendingProcess $process): bool => array_slice((array) $process->command, 1) === ['artisan', 'nightwatch:status'] && $process->path === base_path());
});

it('passes on why the Nightwatch agent did not answer', function (): void {
    config()->set('nightwatch.token', 'nw-token');
    Process::fake(['*' => Process::result(output: "\n   \e[41m ERROR \e[49m  Connection refused\n   [tcp://127.0.0.1:2407].\n", exitCode: 1)]);

    Livewire::test('pages::admin.settings-monitoring')
        ->call('checkNightwatch')
        ->assertDispatched('toast-show', toastSays('The Nightwatch agent didn’t answer: Connection refused [tcp://127.0.0.1:2407].'));
});

it('stops running tests after five a minute', function (): void {
    $transport = fakeSentryTransport();
    config()->set('nightwatch.token', 'nw-token');
    Process::fake();

    $component = Livewire::test('pages::admin.settings-monitoring');

    foreach (range(1, 5) as $ignored) {
        $component->call('testSentry');
    }

    $component->call('checkNightwatch')
        ->assertDispatched('toast-show', toastSays('That’s a lot of tests. Please wait a moment and try again.'));

    expect($transport->events)->toHaveCount(5);
    Process::assertNothingRan();
});
