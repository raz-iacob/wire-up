<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Submission;
use App\Notifications\SubmissionReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final readonly class CreateSubmissionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $recipients
     */
    public function handle(array $attributes, array $recipients = []): Submission
    {
        return DB::transaction(function () use ($attributes, $recipients): Submission {
            $submission = Submission::query()->create($attributes);

            if ($recipients !== []) {
                Notification::route('mail', $recipients)->notify(new SubmissionReceived($submission));
            }

            return $submission;
        });
    }
}
