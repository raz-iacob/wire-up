<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\Submission;
use App\Services\ActivityLog;

final readonly class DeleteSubmissionAction
{
    public function handle(Submission $submission): void
    {
        $submission->delete();

        ActivityLog::record(ActivityEvent::SUBMISSION_DELETED, $submission);
    }
}
