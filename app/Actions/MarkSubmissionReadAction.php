<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Submission;

final readonly class MarkSubmissionReadAction
{
    public function handle(Submission $submission, bool $read = true): void
    {
        if ($read && $submission->read_at === null) {
            $submission->forceFill(['read_at' => now()])->save();
        }

        if (! $read && $submission->read_at !== null) {
            $submission->forceFill(['read_at' => null])->save();
        }
    }
}
