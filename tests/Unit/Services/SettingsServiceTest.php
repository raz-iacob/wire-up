<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Settings;
use App\Services\SettingsService;

it('returns the configured currency with its symbol and decimals', function (): void {
    Settings::set(['currency' => 'GBP']);

    expect(SettingsService::current()->currency())->toBe('GBP')
        ->and(SettingsService::current()->currencySymbol())->toBe('£')
        ->and(SettingsService::current()->currencyDecimals())->toBe(2);
});

it('ignores an unknown configured currency and falls back to a valid one', function (): void {
    Settings::set(['currency' => 'ZZZ']);

    expect(config()->has('currencies.'.SettingsService::current()->currency()))->toBeTrue();
});

it('formats money with the currency symbol and grouping', function (): void {
    Settings::set(['currency' => 'USD']);

    expect(SettingsService::current()->formatMoney(1234.5))->toBe('$1,234.50')
        ->and(SettingsService::current()->formatMoney('99'))->toBe('$99.00')
        ->and(SettingsService::current()->formatMoney(null))->toBe('')
        ->and(SettingsService::current()->formatMoney('nope'))->toBe('');
});

it('formats zero-decimal currencies without decimals', function (): void {
    Settings::set(['currency' => 'JPY']);

    expect(SettingsService::current()->formatMoney(1500))->toBe('¥1,500');
});

it('maps the default locale region to its currency', function (): void {
    Locale::query()->where('code', 'en')->update(['active' => true, 'regional' => 'de-DE']);
    cache()->forget('site-locales');

    expect(SettingsService::deduceCurrency())->toBe('EUR');
});

it('deduces USD from the seeded en-US default locale', function (): void {
    expect(SettingsService::deduceCurrency())->toBe('USD');
});

it('falls back to USD when the default locale has no usable region', function (): void {
    Locale::query()->update(['active' => false, 'regional' => null]);
    cache()->forget('site-locales');

    expect(SettingsService::deduceCurrency())->toBe('USD');
});
