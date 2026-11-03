<?php

declare(strict_types=1);

use App\Models\ErrorLog;
use App\Models\User;
use App\Notifications\ErrorReported;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

it('logs each distinct error once and counts how often it happens, even with nowhere to send reports', function (): void {
    report(errorAtLine(10));
    report(errorAtLine(10));
    report(new RuntimeException('Something else broke'));

    $this->travel(5)->minutes();
    report(errorAtLine(10, 'It broke again'));

    expect(ErrorLog::query()->count())->toBe(2);

    $error = ErrorLog::query()->where('line', 10)->sole();

    expect($error->only(['type', 'message', 'occurrences', 'address', 'user_id', 'resolved_at']))->toBe([
        'type' => RuntimeException::class,
        'message' => 'It broke again',
        'occurrences' => 3,
        'address' => null,
        'user_id' => null,
        'resolved_at' => null,
    ])
        ->and($error->file)->toBe('tests/Pest.php')
        ->and($error->last_seen_at->gt($error->created_at))->toBeTrue()
        ->and($error->trace)->not->toBeEmpty()
        ->and($error->trace[0])->toMatch('/^[\w\/.-]+\.php:\d+ .+\(\)$/')
        ->and($error->context)->toHaveKeys(['version', 'php', 'environment']);
});

it('reopens a resolved error that happens again and reports it straight away', function (): void {
    Notification::fake();
    reportErrorsTo(slack: '');

    report(errorAtLine(10));
    ErrorLog::query()->sole()->resolve(User::factory()->create());
    report(errorAtLine(10));

    expect(ErrorLog::query()->sole()->only(['occurrences', 'resolved_at', 'resolved_by']))->toBe(['occurrences' => 2, 'resolved_at' => null, 'resolved_by' => null]);

    Notification::assertSentOnDemandTimes(ErrorReported::class, 2);
});

it('still reports an error when the log cannot be written', function (): void {
    Notification::fake();
    reportErrorsTo(slack: '');
    Schema::drop('error_logs');

    report(new RuntimeException('The database is having a bad day'));

    Notification::assertSentOnDemand(
        ErrorReported::class,
        fn (ErrorReported $notification, array $channels, AnonymousNotifiable $notifiable): bool => ! $notification->error->exists
            && $notification->toMail($notifiable)->actionUrl === null
            && $notification->error->message === 'The database is having a bad day',
    );
});

it('removes errors nobody has seen for 90 days', function (): void {
    $stale = ErrorLog::factory()->create(['last_seen_at' => now()->subDays(ErrorLog::DAYS_KEPT + 1)]);
    $recent = ErrorLog::factory()->resolved()->create(['last_seen_at' => now()->subDays(ErrorLog::DAYS_KEPT - 1)]);

    $this->artisan('model:prune', ['--model' => [ErrorLog::class]])->assertSuccessful();

    expect(ErrorLog::query()->pluck('id')->all())->toBe([$recent->id])
        ->and($stale->fresh())->toBeNull();
});
