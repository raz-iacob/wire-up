<?php

declare(strict_types=1);

use App\Actions\CreateSubmissionAction;
use App\Models\Submission;
use App\Notifications\SubmissionReceived;
use App\Services\SlackWebhookChannel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('creates a submission from attributes', function (): void {
    Notification::fake();

    $submission = (new CreateSubmissionAction)->handle([
        'type' => 'contact',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => 'Hello',
        'country' => 'GB',
        'metadata' => ['x' => ['label' => 'X', 'value' => 'y']],
    ]);

    expect($submission)->toBeInstanceOf(Submission::class);
    $this->assertDatabaseHas('submissions', [
        'id' => $submission->id,
        'type' => 'contact',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'country' => 'GB',
    ]);
    expect($submission->metadata)->toBe(['x' => ['label' => 'X', 'value' => 'y']]);

    Notification::assertNothingSent();
});

it('notifies the given recipients when creating the submission', function (): void {
    Notification::fake();

    $submission = (new CreateSubmissionAction)->handle(
        ['type' => 'contact', 'name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi'],
        ['owner@example.com', 'second@example.com'],
    );

    $this->assertDatabaseHas('submissions', ['id' => $submission->id]);

    Notification::assertSentOnDemand(
        SubmissionReceived::class,
        fn (SubmissionReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['owner@example.com', 'second@example.com'],
    );
});

it('notifies slack when a webhook is configured, even without mail recipients', function (): void {
    Notification::fake();
    config()->set('services.slack.webhook_url', 'https://hooks.slack.com/services/T0/B0/xyz');

    (new CreateSubmissionAction)->handle(['type' => 'contact', 'name' => 'Ada', 'message' => 'Hi']);

    Notification::assertSentOnDemand(
        SubmissionReceived::class,
        fn (SubmissionReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes[SlackWebhookChannel::class] ?? null) === 'https://hooks.slack.com/services/T0/B0/xyz',
    );
});

it('notifies both mail recipients and slack together', function (): void {
    Notification::fake();
    config()->set('services.slack.webhook_url', 'https://hooks.slack.com/services/T0/B0/xyz');

    (new CreateSubmissionAction)->handle(
        ['type' => 'contact', 'name' => 'Ada', 'message' => 'Hi'],
        ['owner@example.com'],
    );

    Notification::assertSentOnDemandTimes(SubmissionReceived::class, 2);
});
