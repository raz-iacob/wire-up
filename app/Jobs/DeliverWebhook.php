<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ActivityEvent;
use App\Models\WebhookDelivery;
use App\Services\ActivityLog;
use App\Services\PublicUrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

#[Timeout(30)]
#[Tries(5)]
final class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @var list<int>
     */
    public const array BACKOFF = [60, 300, 900, 3600];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(PublicUrlGuard $guard): void
    {
        $delivery = WebhookDelivery::query()->with('webhook')->find($this->deliveryId);

        if ($delivery === null || $delivery->succeeded()) {
            return;
        }

        $delivery->increment('attempts');

        try {
            $guard->assertPublic($delivery->webhook->url);
        } catch (InvalidArgumentException $exception) {
            $this->record($delivery, null, __('The address points to a private network, so it was not called.'));
            $this->fail($exception);

            return;
        }

        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;

        try {
            $response = Http::withBody($body, 'application/json')
                ->withUserAgent('Wire Up webhooks')
                ->withHeaders([
                    'X-Wire-Up-Event' => $delivery->event,
                    'X-Wire-Up-Delivery' => $delivery->uuid,
                    'X-Wire-Up-Timestamp' => $timestamp,
                    'X-Wire-Up-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $delivery->webhook->secret),
                ])
                ->withOptions(['allow_redirects' => false])
                ->timeout(10)
                ->post($delivery->webhook->url);
        } catch (Throwable $exception) {
            $this->retryOrFail($delivery, null, Str::limit($exception->getMessage(), 500));

            return;
        }

        if ($response->successful()) {
            $this->record($delivery, $response->status(), null, Str::limit($response->body(), 500));
            $delivery->update(['delivered_at' => now()]);
            $delivery->webhook->update(['last_delivered_at' => now()]);

            return;
        }

        $this->retryOrFail($delivery, $response->status(), __('The endpoint answered with HTTP :status.', ['status' => $response->status()]), Str::limit($response->body(), 500));
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->with('webhook')->find($this->deliveryId);

        ActivityLog::record(ActivityEvent::WEBHOOK_FAILED, $delivery?->webhook, [
            'event' => $delivery?->event,
            'reason' => $delivery->error ?? (string) $exception?->getMessage(),
        ]);
    }

    private function retryOrFail(WebhookDelivery $delivery, ?int $status, string $error, ?string $excerpt = null): void
    {
        $this->record($delivery, $status, $error, $excerpt);

        if ($this->attempts() >= 5) {
            $this->fail(new RuntimeException($error));

            return;
        }

        $this->release(self::BACKOFF[$this->attempts() - 1] ?? 3600);
    }

    private function record(WebhookDelivery $delivery, ?int $status, ?string $error, ?string $excerpt = null): void
    {
        $delivery->update(['status_code' => $status, 'error' => $error, 'response_excerpt' => $excerpt]);
        $delivery->webhook->update(['last_status' => $status, 'last_error' => $error]);
    }
}
