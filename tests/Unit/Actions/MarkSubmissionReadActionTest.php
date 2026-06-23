<?php

declare(strict_types=1);

use App\Actions\MarkSubmissionReadAction;
use App\Models\Submission;

it('marks an unread submission as read', function (): void {
    $submission = Submission::factory()->create();

    (new MarkSubmissionReadAction)->handle($submission);

    expect($submission->fresh()->read_at)->not->toBeNull();
});

it('does not overwrite an existing read timestamp', function (): void {
    $readAt = now()->subDay();
    $submission = Submission::factory()->create(['read_at' => $readAt]);

    (new MarkSubmissionReadAction)->handle($submission, true);

    expect($submission->fresh()->read_at->toDateTimeString())->toBe($readAt->toDateTimeString());
});

it('marks a read submission as unread', function (): void {
    $submission = Submission::factory()->read()->create();

    (new MarkSubmissionReadAction)->handle($submission, false);

    expect($submission->fresh()->read_at)->toBeNull();
});

it('leaves an already-unread submission unchanged', function (): void {
    $submission = Submission::factory()->create();

    (new MarkSubmissionReadAction)->handle($submission, false);

    expect($submission->fresh()->read_at)->toBeNull();
});
