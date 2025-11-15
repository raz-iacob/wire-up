<?php

declare(strict_types=1);

use App\Models\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

it('binds locales singleton with active locale codes and cache them', function (): void {
    Cache::forget('site-locales');

    $fromCache = Cache::get('site-locales');
    expect($fromCache?->toArray())->toBeNull();

    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $locales = app('locales');

    expect($locales)->toBeInstanceOf(Collection::class)
        ->and($locales->toArray())->toBe(['en', 'fr']);

    $fromCache = Cache::get('site-locales');
    expect($fromCache->toArray())->toBe(['en', 'fr']);
});
