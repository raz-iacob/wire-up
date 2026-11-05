<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Submission;
use App\Services\NewsletterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use RuntimeException;
use Throwable;

#[Timeout(30)]
#[Tries(5)]
final class SubscribeToNewsletter implements ShouldQueue
{
    use Queueable;

    /**
     * @var list<int>
     */
    public const array BACKOFF = [60, 300, 900, 3600];

    public function __construct(public readonly int $submissionId) {}

    public function handle(NewsletterService $newsletter): void
    {
        $submission = Submission::query()->find($this->submissionId);
        $provider = $newsletter->provider();

        if ($submission === null || $provider === null || ! is_string($submission->email) || data_get($submission->metadata, 'newsletter.status') === 'synced') {
            return;
        }

        $result = $newsletter->subscribe($submission->email, $submission->name, $submission->ip);

        if ($result->synced) {
            $this->record($submission, $provider, 'synced');

            return;
        }

        if ($result->retryable && $this->attempts() < 5) {
            $this->record($submission, $provider, 'retrying', $result->message);
            $this->release(self::BACKOFF[$this->attempts() - 1] ?? 3600);

            return;
        }

        $this->record($submission, $provider, 'failed', $result->message);
        $this->fail(new RuntimeException($result->message));
    }

    public function failed(?Throwable $exception): void
    {
        $submission = Submission::query()->find($this->submissionId);
        $status = data_get($submission?->metadata, 'newsletter');

        if ($submission === null || ! is_array($status) || in_array($status['status'] ?? null, ['synced', 'failed'], true)) {
            return;
        }

        $this->record($submission, is_string($status['provider'] ?? null) ? $status['provider'] : '', 'failed', (string) $exception?->getMessage());
    }

    private function record(Submission $submission, string $provider, string $status, ?string $error = null): void
    {
        $submission->update(['metadata' => [
            ...(is_array($submission->metadata) ? $submission->metadata : []),
            'newsletter' => ['provider' => $provider, 'status' => $status, 'error' => $error, 'at' => now()->toIso8601String()],
        ]]);
    }
}
