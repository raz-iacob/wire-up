<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Notifications\BackupFailed;
use App\Services\SlackWebhookChannel;
use Illuminate\Notifications\AnonymousNotifiable;

it('goes to every channel it was routed to, and to mail otherwise', function (): void {
    $routed = (new AnonymousNotifiable)->route('mail', 'owner@example.com')->route(SlackWebhookChannel::class, 'https://hooks.slack.com/services/T/B/x');

    expect(new BackupFailed('boom')->via($routed))->toBe(['mail', SlackWebhookChannel::class])
        ->and(new BackupFailed('boom')->via(new AnonymousNotifiable))->toBe(['mail']);
});

it('builds a branded failure mail with the reason', function (): void {
    Settings::set(['title' => ['en' => 'Acme Studio']]);

    $mail = new BackupFailed('disk full')->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('The automatic backup of Acme Studio failed')
        ->and($mail->level)->toBe('error')
        ->and($mail->actionUrl)->toBe(route('admin.settings-backups'))
        ->and(implode(' ', $mail->introLines))->toContain('Reason: disk full');
});

it('builds a slack message with the reason escaped', function (): void {
    $payload = new BackupFailed('mysqldump <error> & more')->toSlackWebhook();

    expect($payload['text'])->toContain('automatic backup')
        ->and($payload['blocks'][1]['text']['text'])->toBe('mysqldump &lt;error&gt; &amp; more')
        ->and($payload['blocks'][2]['elements'][0]['url'])->toBe(route('admin.settings-backups'));
});
