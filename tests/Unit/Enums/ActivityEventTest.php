<?php

declare(strict_types=1);

use App\Enums\ActivityArea;
use App\Enums\ActivityEvent;
use App\Enums\RevisionSource;

it('gives every event its own label', function (): void {
    $labels = array_map(fn (ActivityEvent $event): string => $event->label(), ActivityEvent::cases());

    expect(array_unique($labels))->toHaveSameSize(ActivityEvent::cases())
        ->and(ActivityEvent::PAGE_PUBLISHED->label())->toBe('Published page')
        ->and(ActivityEvent::SIGN_IN_FAILED->label())->toBe('Failed sign-in');
});

it('files each event under the area it belongs to', function (ActivityEvent $event, ActivityArea $area): void {
    expect($event->area())->toBe($area);
})->with([
    [ActivityEvent::PAGE_CREATED, ActivityArea::CONTENT],
    [ActivityEvent::RECORD_DELETED, ActivityArea::CONTENT],
    [ActivityEvent::RECORD_TYPE_UPDATED, ActivityArea::CONTENT],
    [ActivityEvent::CATEGORY_CREATED, ActivityArea::CONTENT],
    [ActivityEvent::MEDIA_UPLOADED, ActivityArea::CONTENT],
    [ActivityEvent::USER_INVITED, ActivityArea::PEOPLE],
    [ActivityEvent::SIGNED_IN, ActivityArea::SIGN_INS],
    [ActivityEvent::SETTINGS_UPDATED, ActivityArea::SETTINGS],
    [ActivityEvent::ROLE_DELETED, ActivityArea::SETTINGS],
    [ActivityEvent::REDIRECT_SAVED, ActivityArea::SETTINGS],
    [ActivityEvent::ORDER_REFUNDED, ActivityArea::SHOP],
    [ActivityEvent::SUBMISSION_DELETED, ActivityArea::INBOX],
    [ActivityEvent::BACKUP_FAILED, ActivityArea::SYSTEM],
]);

it('labels each area with its own icon and lists the events in it', function (): void {
    expect(array_map(fn (ActivityArea $area): string => $area->label(), ActivityArea::cases()))->toBe(['Content', 'People', 'Sign-ins', 'Settings', 'Shop', 'Inbox', 'System'])
        ->and(array_unique(array_map(fn (ActivityArea $area): string => $area->icon(), ActivityArea::cases())))->toHaveCount(7)
        ->and(ActivityArea::SIGN_INS->events())->toBe(['auth.signed_in', 'auth.signed_out', 'auth.failed', 'auth.locked_out', 'auth.two_factor_failed'])
        ->and(array_merge(...array_map(fn (ActivityArea $area): array => $area->events(), ActivityArea::cases())))->toHaveSameSize(ActivityEvent::cases());
});

it('labels work the system did on its own', function (): void {
    expect(RevisionSource::SYSTEM->label())->toBe('System');
});
