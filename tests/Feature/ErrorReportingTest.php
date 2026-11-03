<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Models\User;
use App\Notifications\ErrorReported;
use App\Services\ErrorReporter;
use App\Services\SlackWebhookChannel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

function reportErrorsTo(string $email = 'dev@example.com', string $slack = 'https://hooks.slack.com/services/T0/B0/dev'): void
{
    Settings::set(['error_report_email' => $email, 'error_report_slack_webhook_url' => $slack]);
}

function errorAtLine(int $line): RuntimeException
{
    $exception = new RuntimeException('Something broke');
    new ReflectionProperty(Exception::class, 'line')->setValue($exception, $line);

    return $exception;
}

it('emails and messages Slack about an unexpected error, leaving out the query string', function (): void {
    Notification::fake();
    reportErrorsTo();
    Route::post('/explode', fn () => throw new RuntimeException('The flux capacitor broke'));

    $this->actingAs($user = User::factory()->create())
        ->post('/explode?token=secret-token')
        ->assertServerError();

    Notification::assertSentOnDemandTimes(ErrorReported::class, 2);
    Notification::assertSentOnDemand(ErrorReported::class, function (ErrorReported $notification, array $channels, AnonymousNotifiable $notifiable) use ($user): bool {
        $mail = implode("\n", $notification->toMail($notifiable)->introLines);
        $slack = (string) json_encode($notification->toSlackWebhook());

        return $channels === ['mail']
            && $notifiable->routes['mail'] === 'dev@example.com'
            && str_contains($mail, '**RuntimeException:** The flux capacitor broke')
            && str_contains($mail, 'Where: tests/Feature/ErrorReportingTest.php:')
            && str_contains($mail, 'Address: POST '.url('/explode')."\n")
            && str_contains($mail, "Signed in: User #{$user->id}")
            && str_contains($slack, 'The flux capacitor broke')
            && ! str_contains($mail.$slack, 'secret-token');
    });
    Notification::assertSentOnDemand(
        ErrorReported::class,
        fn (ErrorReported $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === [SlackWebhookChannel::class]
            && $notifiable->routes[SlackWebhookChannel::class] === 'https://hooks.slack.com/services/T0/B0/dev',
    );
});

it('describes an error from the command line or a queue', function (): void {
    Notification::fake();
    reportErrorsTo(slack: '');

    report(new RuntimeException('The queue fell over'));

    Notification::assertSentOnDemand(
        ErrorReported::class,
        fn (ErrorReported $notification): bool => $notification->address === 'Command line or queue'
            && $notification->context['Signed in'] === 'No'
            && $notification->message === 'The queue fell over',
    );
});

it('sends each distinct error at most once an hour', function (): void {
    Notification::fake();
    reportErrorsTo(slack: '');

    report(errorAtLine(10));
    report(errorAtLine(10));
    report(errorAtLine(11));

    Notification::assertSentOnDemandTimes(ErrorReported::class, 2);

    $this->travel(61)->minutes();
    report(errorAtLine(10));

    Notification::assertSentOnDemandTimes(ErrorReported::class, 3);
});

it('sends no more than twenty error reports an hour', function (): void {
    Notification::fake();
    reportErrorsTo(slack: '');

    foreach (range(1, ErrorReporter::MAX_PER_HOUR + 5) as $line) {
        report(errorAtLine($line));
    }

    Notification::assertSentOnDemandTimes(ErrorReported::class, ErrorReporter::MAX_PER_HOUR);
});

it('reports nothing until somewhere to send reports is saved', function (): void {
    Notification::fake();

    report(new RuntimeException('Nobody is listening'));

    Notification::assertNothingSent();
});

it('never reports a visitor asking for a page that does not exist', function (): void {
    Notification::fake();
    reportErrorsTo();

    $this->get('/no-such-page')->assertNotFound();

    Notification::assertNothingSent();
});

it('still messages Slack when email fails, and never reports its own failures', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::sequence()->push('ok')->push('no_service', 404)]);
    reportErrorsTo();
    config()->set('mail.default', 'missing-mailer');

    report(errorAtLine(1));
    report(errorAtLine(2));

    Http::assertSentCount(2);
});
