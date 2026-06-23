<?php

declare(strict_types=1);

use App\Actions\CreateSubmissionAction;
use App\Models\Submission;
use App\Notifications\SubmissionReceived;
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
