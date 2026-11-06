<?php

declare(strict_types=1);

use App\Models\Settings;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('connects slack by persisting the webhook url', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.slack')
        ->set('form.slack_webhook_url', '  https://hooks.slack.com/services/T0/B0/xyz  ')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('slack_webhook_url'))->toBe('https://hooks.slack.com/services/T0/B0/xyz');
});

it('rejects webhook urls that are not slack incoming webhooks', function (string $url, string $rule): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.slack')
        ->set('form.slack_webhook_url', $url)
        ->call('connect')
        ->assertHasErrors(['form.slack_webhook_url' => $rule]);
})->with([
    'empty' => ['', 'required'],
    'not a url' => ['not-a-url', 'url'],
    'not a slack webhook' => ['https://example.com/webhook', 'regex'],
]);

it('tests the typed slack webhook without saving it', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::response('ok')]);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.slack')
        ->set('form.slack_webhook_url', '  https://hooks.slack.com/services/T0/B0/typed  ')
        ->call('test')
        ->assertHasNoErrors();

    Http::assertSent(
        fn ($request): bool => $request->url() === 'https://hooks.slack.com/services/T0/B0/typed',
    );

    expect(Settings::get('slack_webhook_url'))->toBeNull();
});

it('validates the slack webhook before testing it', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.slack')
        ->set('form.slack_webhook_url', 'https://example.com/hook')
        ->call('test')
        ->assertHasErrors(['form.slack_webhook_url' => 'regex']);
});

it('stops testing integrations after five attempts a minute', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::response('ok')]);

    $this->actingAsAdmin();

    $component = Livewire::test('admin.integrations.slack')
        ->set('form.slack_webhook_url', 'https://hooks.slack.com/services/T0/B0/typed');

    foreach (range(1, 6) as $ignored) {
        $component->call('test');
    }

    Http::assertSentCount(5);
});
