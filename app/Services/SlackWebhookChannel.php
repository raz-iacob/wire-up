<?php

declare(strict_types=1);

namespace App\Services;

use App\Notifications\SubmissionReceived;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;

final class SlackWebhookChannel
{
    public function send(object $notifiable, SubmissionReceived $notification): void
    {
        $url = $notifiable instanceof AnonymousNotifiable ? $notifiable->routeNotificationFor(self::class) : null;

        if (! is_string($url) || $url === '') {
            return;
        }

        Http::post($url, $notification->toSlackWebhook())->throw();
    }
}
