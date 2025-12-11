<?php

declare(strict_types=1);

use App\Services\LocalizationService;

it('binds localization singleton', function (): void {
    $localization = resolve('localization');

    expect($localization)->toBeInstanceOf(LocalizationService::class);
});
