<?php

declare(strict_types=1);

use App\Services\LocalizationService;

it('binds localization singleton', function (): void {
    $localization = app('localization');

    expect($localization)->toBeInstanceOf(LocalizationService::class);
});
