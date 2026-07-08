<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Submission;
use App\Notifications\SubmissionReceived;
use App\Services\SlackWebhookChannel;
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

            $webhookUrl = config('services.slack.webhook_url');

            if (is_string($webhookUrl) && $webhookUrl !== '') {
                Notification::route(SlackWebhookChannel::class, $webhookUrl)->notify(new SubmissionReceived($submission));
            }

            return $submission;
        });
    }
}
