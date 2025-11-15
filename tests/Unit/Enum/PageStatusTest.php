<?php

declare(strict_types=1);

use App\Enums\PageStatus;

it('returns correct labels for all statuses', function (): void {
    expect(PageStatus::DRAFT->label())->toBe('Draft')
        ->and(PageStatus::PUBLISHED->label())->toBe('Published')
        ->and(PageStatus::PRIVATE->label())->toBe('Private');
});

it('returns correct colors for all statuses', function (): void {
    expect(PageStatus::DRAFT->color())->toBe('zinc')
        ->and(PageStatus::PUBLISHED->color())->toBe('green')
        ->and(PageStatus::PRIVATE->color())->toBe('orange');
});
