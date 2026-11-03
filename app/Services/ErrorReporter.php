<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ErrorLog;
use App\Notifications\ErrorReported;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

final class ErrorReporter
{
    public const int MAX_PER_HOUR = 20;

    private static bool $sending = false;

    public function report(Throwable $exception): void
    {
        if (self::$sending) {
            return;
        }

        self::$sending = true;

        try {
            $error = ErrorLog::forException($exception);
            $reopened = $error->isResolved();

            rescue(fn (): bool => $error->describe($exception)->save(), false, report: false);

            if ($this->routes() !== [] && ($this->isFirstInAnHour($error->fingerprint) || $reopened)) {
                $this->deliver(new ErrorReported($error));
            }
        } finally {
            self::$sending = false;
        }
    }

    /**
     * @return list<string>
     */
    public function sendTest(): array
    {
        $error = new ErrorLog()->describe(new RuntimeException(__('This is a test error report. Error reports are working.')));

        return $this->deliver(new ErrorReported($error));
    }

    /**
     * @return array<string, string>
     */
    public function routes(): array
    {
        return array_filter([
            'mail' => $this->setting('error_report_email'),
            SlackWebhookChannel::class => $this->setting('error_report_slack_webhook_url'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function deliver(ErrorReported $notification): array
    {
        $failures = [];

        foreach ($this->routes() as $channel => $route) {
            try {
                Notification::sendNow(new AnonymousNotifiable()->route($channel, $route), $notification);
            } catch (Throwable $exception) {
                $failures[] = $exception->getMessage();
            }
        }

        return $failures;
    }

    private function isFirstInAnHour(string $fingerprint): bool
    {
        return (bool) rescue(
            fn (): bool => cache()->add('error-report:'.$fingerprint, true, now()->addHour())
                && RateLimiter::attempt('error-reports', self::MAX_PER_HOUR, fn (): bool => true, 3600),
            false,
            report: false,
        );
    }

    private function setting(string $key): string
    {
        $value = config('site.'.$key);

        return is_string($value) ? $value : '';
    }
}
