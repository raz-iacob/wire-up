<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function signedInDeveloper(): User
{
    $developer = User::factory()->developer()->create(['active' => true]);
    test()->actingAs($developer);

    return $developer;
}

it('opens only for developers, linked from the settings sidebar', function (): void {
    $this->actingAs(User::factory()->owner()->create(['active' => true]));

    $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.settings-developer'));
    $this->get(route('admin.settings-developer'))->assertForbidden();

    signedInDeveloper();

    $this->get(route('admin.settings-developer'))
        ->assertOk()
        ->assertSee(route('admin.settings-developer'))
        ->assertSee('Error reports');
});

it('saves where error reports go and never sends the saved webhook back to the browser', function (): void {
    signedInDeveloper();

    Livewire::test('pages::admin.settings-developer')
        ->set('error_report_email', '  dev@example.com  ')
        ->set('error_report_slack_webhook_url', 'https://hooks.slack.com/services/T0/B0/secret-AB12')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSet('error_report_slack_webhook_url', '');

    expect(Settings::get('error_report_email'))->toBe('dev@example.com')
        ->and(Settings::get('error_report_slack_webhook_url'))->toBe('https://hooks.slack.com/services/T0/B0/secret-AB12');

    $this->get(route('admin.settings-developer'))
        ->assertDontSee('secret-AB12')
        ->assertSee('https://hooks.slack.com/services/…AB12')
        ->assertSee('Leave blank to keep the saved webhook.');
});

it('keeps the saved webhook when its field is left blank, and removes it on request', function (): void {
    Settings::set(['error_report_slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/saved']);
    signedInDeveloper();

    $component = Livewire::test('pages::admin.settings-developer')
        ->set('error_report_email', 'dev@example.com')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('error_report_slack_webhook_url'))->toBe('https://hooks.slack.com/services/T0/B0/saved');

    $component->call('removeSlackWebhook')->assertDispatched('toast-show', toastSays('Slack webhook removed.'));

    expect(Settings::get('error_report_slack_webhook_url'))->toBe('');
});

it('rejects an invalid email address or a webhook that is not from Slack', function (string $field, string $value, string $message): void {
    signedInDeveloper();

    Livewire::test('pages::admin.settings-developer')
        ->set($field, $value)
        ->call('update')
        ->assertHasErrors([$field])
        ->assertSee($message);
})->with([
    'email' => ['error_report_email', 'not-an-email', 'Enter a valid email address.'],
    'webhook' => ['error_report_slack_webhook_url', 'https://example.com/hook', 'Enter a valid Slack incoming webhook URL, like https://hooks.slack.com/services/…'],
]);

it('sends a test report to the saved destinations and says when one fails', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::sequence()->push('ok')->push('no_service', 404)]);
    Settings::set(['error_report_email' => 'dev@example.com', 'error_report_slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/dev']);
    signedInDeveloper();

    Livewire::test('pages::admin.settings-developer')
        ->call('sendTest')
        ->assertDispatched('toast-show', toastSays('Test report sent.'))
        ->call('sendTest')
        ->assertDispatched('toast-show', fn (string $event, array $payload): bool => str_starts_with((string) ($payload['slots']['text'] ?? ''), 'The test report could not be sent:'));

    Http::assertSentCount(2);
});

it('asks for somewhere to send reports before sending a test', function (): void {
    signedInDeveloper();

    Livewire::test('pages::admin.settings-developer')
        ->call('sendTest')
        ->assertDispatched('toast-show', toastSays('Save an email address or a Slack webhook first.'));
});

it('stops sending test reports after five a minute', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
    Settings::set(['error_report_slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/dev']);
    signedInDeveloper();

    $component = Livewire::test('pages::admin.settings-developer');

    foreach (range(1, 6) as $ignored) {
        $component->call('sendTest');
    }

    Http::assertSentCount(5);
});
