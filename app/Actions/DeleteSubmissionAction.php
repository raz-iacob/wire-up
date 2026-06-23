<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Submission;

final readonly class DeleteSubmissionAction
{
    public function handle(Submission $submission): void
    {
        $submission->delete();
    }
}
