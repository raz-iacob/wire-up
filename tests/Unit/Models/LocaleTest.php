<?php

declare(strict_types=1);

use App\Models\Locale;
use Illuminate\Database\QueryException;

test('to array', function (): void {
    $locale = Locale::factory()->create()->refresh();

    expect(array_keys($locale->toArray()))
        ->toBe([
            'id',
            'code',
            'name',
            'endonym',
            'script',
            'regional',
            'rtl',
            'active',
            'published',
            'created_at',
            'updated_at',
        ]);
});

test('code is unique', function (): void {
    $existingLocale = Locale::factory()->create();

    expect(fn () => Locale::factory()->create(['code' => $existingLocale->code]))
        ->toThrow(QueryException::class);
});

it('can filter by active status using active scope', function (): void {
    $activeLocales = Locale::active()->get();
    $inactiveLocales = Locale::query()->where('active', false)->get();

    expect($activeLocales)->toHaveCount(1)
        ->and($inactiveLocales)->toHaveCount(61);
});
