<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Notifications\SystemUpdateCompleted;
use Illuminate\Notifications\AnonymousNotifiable;

it('sends over the mail channel', function (): void {
    expect(new SystemUpdateCompleted('v1.0.0', successful: true)->via(new AnonymousNotifiable))->toBe(['mail']);
});

it('builds a branded success mail', function (): void {
    Settings::set(['title' => ['en' => 'Acme Studio']]);

    $mail = new SystemUpdateCompleted('v1.2.0', successful: true)->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('Acme Studio has been updated to v1.2.0')
        ->and($mail->from)->toBe([config('mail.from.address'), 'Acme Studio'])
        ->and($mail->actionUrl)->toBe(route('admin.settings-updates'))
        ->and(implode(' ', $mail->introLines))->toContain('completed successfully');
});

it('builds a failure mail with the step, output and recovery hint', function (): void {
    $mail = new SystemUpdateCompleted('v1.2.0', successful: false, step: 'Run database migrations', output: 'migration exploded')
        ->toMail(new AnonymousNotifiable);

    $body = implode(' ', $mail->introLines);

    expect($mail->subject)->toContain('update to v1.2.0 failed')
        ->and($body)->toContain('Run database migrations')
        ->and($body)->toContain('migration exploded')
        ->and($body)->toContain('php artisan up')
        ->and($mail->level)->toBe('error');
});

it('omits the output line when there is none', function (): void {
    $mail = new SystemUpdateCompleted('v1.2.0', successful: false, step: 'Fetch releases')
        ->toMail(new AnonymousNotifiable);

    expect(implode(' ', $mail->introLines))->not->toContain('Last output');
});
