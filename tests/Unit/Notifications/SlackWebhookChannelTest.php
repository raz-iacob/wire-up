<?php

declare(strict_types=1);

use App\Models\Submission;
use App\Notifications\SubmissionReceived;
use App\Services\SlackWebhookChannel;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;

it('posts the notification payload to the routed webhook url', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::response('ok')]);

    $submission = Submission::factory()->create(['form_name' => 'Contact', 'name' => 'Ada']);
    $notifiable = (new AnonymousNotifiable)->route(SlackWebhookChannel::class, 'https://hooks.slack.com/services/T0/B0/xyz');

    new SlackWebhookChannel()->send($notifiable, new SubmissionReceived($submission));

    Http::assertSent(fn ($request): bool => (string) $request->url() === 'https://hooks.slack.com/services/T0/B0/xyz'
        && $request['text'] === 'New submission from Contact'
        && is_array($request['blocks']));
});

it('does nothing for notifiables without a webhook route', function (object $notifiable): void {
    Http::fake();

    new SlackWebhookChannel()->send($notifiable, new SubmissionReceived(Submission::factory()->make()));

    Http::assertNothingSent();
})->with([
    'non-anonymous notifiable' => [new stdClass],
    'anonymous without a route' => [new AnonymousNotifiable],
    'anonymous with an empty route' => [(new AnonymousNotifiable)->route(SlackWebhookChannel::class, '')],
]);

it('propagates webhook failures', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::response('invalid_payload', 400)]);

    $notifiable = (new AnonymousNotifiable)->route(SlackWebhookChannel::class, 'https://hooks.slack.com/services/T0/B0/xyz');

    new SlackWebhookChannel()->send($notifiable, new SubmissionReceived(Submission::factory()->create()));
})->throws(RequestException::class);
