<?php

declare(strict_types=1);

use App\Enums\ContentStatus;

it('returns correct labels for all statuses', function (): void {
    expect(ContentStatus::DRAFT->label())->toBe(__('Draft'))
        ->and(ContentStatus::PUBLISHED->label())->toBe(__('Published'))
        ->and(ContentStatus::PRIVATE->label())->toBe(__('Private'))
        ->and(ContentStatus::SCHEDULED->label())->toBe(__('Scheduled'));
});

it('returns correct colors for all statuses', function (): void {
    expect(ContentStatus::DRAFT->color())->toBe('zinc')
        ->and(ContentStatus::PUBLISHED->color())->toBe('green')
        ->and(ContentStatus::PRIVATE->color())->toBe('red')
        ->and(ContentStatus::SCHEDULED->color())->toBe('orange');
});

it('returns a text color class for all statuses', function (): void {
    expect(ContentStatus::DRAFT->textColor())->toContain('text-zinc')
        ->and(ContentStatus::PUBLISHED->textColor())->toContain('text-green')
        ->and(ContentStatus::PRIVATE->textColor())->toContain('text-red')
        ->and(ContentStatus::SCHEDULED->textColor())->toContain('text-orange');
});
