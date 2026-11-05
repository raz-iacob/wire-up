<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\WebhookEvent;
use App\Models\Block;
use App\Models\Submission;
use App\Notifications\SubmissionReceived;
use App\Services\SlackWebhookChannel;
use App\Services\Webhooks;
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
            if (isset($attributes['block_id']) && ! Block::query()->whereKey($attributes['block_id'])->exists()) {
                $attributes['block_id'] = null;
            }

            $submission = Submission::query()->create($attributes);

            $webhooks = resolve(Webhooks::class);
            $webhooks->dispatch(
                $submission->type === 'newsletter' ? WebhookEvent::NEWSLETTER_SUBSCRIBED : WebhookEvent::SUBMISSION_CREATED,
                $webhooks->submissionData($submission),
            );

            if ($recipients !== []) {
                Notification::route('mail', $recipients)->notify(new SubmissionReceived($submission));
            }

            $webhookUrl = config('services.slack.webhook_url');

            if ($submission->type !== 'newsletter' && is_string($webhookUrl) && $webhookUrl !== '') {
                Notification::route(SlackWebhookChannel::class, $webhookUrl)->notify(new SubmissionReceived($submission));
            }

            return $submission;
        });
    }
}
