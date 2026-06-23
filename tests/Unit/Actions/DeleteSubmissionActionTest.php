<?php

declare(strict_types=1);

use App\Actions\DeleteSubmissionAction;
use App\Models\Submission;

it('deletes the submission', function (): void {
    $submission = Submission::factory()->create();

    (new DeleteSubmissionAction)->handle($submission);

    $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
});
