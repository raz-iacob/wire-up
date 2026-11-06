<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('starts updates, backups and imports from the scheduler every minute, one run at a time', function (): void {
    $this->artisan('schedule:list')->assertExitCode(0);

    $event = collect(resolve(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'queue:work --queue=system --stop-when-empty --tries=1'));

    expect($event?->expression)->toBe('* * * * *')
        ->and($event?->withoutOverlapping)->toBeTrue()
        ->and($event?->expiresAt)->toBe(70)
        ->and($event?->runInBackground)->toBeTrue();
});
