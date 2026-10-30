<?php

declare(strict_types=1);

use App\Enums\RedirectOrigin;

it('labels where each redirect came from', function (): void {
    expect(RedirectOrigin::MANUAL->label())->toBe('Manual')
        ->and(RedirectOrigin::AUTOMATIC->label())->toBe('Automatic')
        ->and(RedirectOrigin::IMPORT->label())->toBe('Imported');
});
