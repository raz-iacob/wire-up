<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Services\LocalizationService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

it('returns active locales from cache', function (): void {
    Locale::query()->whereIn('code', ['en', 'nl'])->update(['active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    $activeLocales = $localization->getActiveLocales();

    expect($activeLocales)->toBeArray()
        ->and($activeLocales)->toHaveKeys(['en', 'nl']);
});

it('returns active locale codes as collection', function (): void {
    Locale::query()->whereIn('code', ['en', 'nl'])->update(['active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    $codes = $localization->getActiveLocaleCodes();

    expect($codes)->toBeInstanceOf(Collection::class)
        ->and($codes->all())->toBe(['en', 'nl']);
});

it('checks if locale is active', function (): void {
    Locale::query()->where('code', 'en')->update(['active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    expect($localization->isActiveLocale('en'))->toBeTrue()
        ->and($localization->isActiveLocale('fr'))->toBeFalse();
});

it('sets locale from request segment', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    Cache::forget('site-locales');

    $request = Request::create('/nl/');
    $app = app();
    $config = resolve(Repository::class);
    $translator = resolve(Translator::class);

    $localization = new LocalizationService($app, $config, $request, $translator);

    $result = $localization->setLocale();

    expect($result)->toBe('nl')
        ->and(app()->getLocale())->toBe('nl');
});

it('sets default locale when segment is not active', function (): void {
    $request = Request::create('/fr/');
    $app = app();
    $config = resolve(Repository::class);
    $translator = resolve(Translator::class);

    $localization = new LocalizationService($app, $config, $request, $translator);

    $result = $localization->setLocale();

    expect($result)->toBeNull()
        ->and(app()->getLocale())->toBe('en');
});

it('gets current locale regional', function (): void {
    Locale::query()->where('code', 'en')->update(['regional' => 'en_US', 'active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    expect($localization->getCurrentLocaleRegional())->toBe('en_US');
});

it('gets current locale regional when null', function (): void {
    Locale::query()->where('code', 'en')->update(['regional' => null, 'active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    expect($localization->getCurrentLocaleRegional())->toBeNull();
});

it('gets locale name', function (): void {
    Locale::query()->where('code', 'en')->update(['name' => 'English', 'active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    expect($localization->getLocaleName('en'))->toBe('English');
});

it('gets locale native name', function (): void {
    Locale::query()->where('code', 'en')->update(['endonym' => 'English', 'active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    expect($localization->getLocaleNative('en'))->toBe('English');
});

it('gets locale direction', function (): void {
    Locale::query()->where('code', 'en')->update(['rtl' => false, 'active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    expect($localization->getLocaleDirection('en'))->toBe('ltr');
});

it('strips default locale from url', function (): void {
    $localization = resolve(LocalizationService::class);

    $url = $localization->stripDefaultLocale('/en/dashboard');

    expect($url)->toBe(url('/dashboard'));
});

it('generates localized url', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    $url = $localization->getLocalizedURL('/dashboard', 'nl');

    expect($url)->toContain('/nl/dashboard');
});

it('removed the default locale from url', function (): void {
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    $url = $localization->getLocalizedURL('/en/dashboard', 'en');

    expect($url)->toBe('/dashboard');
});

it('generates localized url for full url', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    $url = $localization->getLocalizedURL('https://example.com/en/dashboard', 'nl');

    expect($url)->toBe('https://example.com/nl/dashboard');
});

it('sets locale with regional', function (): void {
    Locale::query()->where('code', 'en')->update(['regional' => 'en_US', 'active' => true]);
    Cache::forget('site-locales');

    $request = Request::create('/en/');
    $app = app();
    $config = resolve(Repository::class);
    $translator = resolve(Translator::class);

    $localization = new LocalizationService($app, $config, $request, $translator);

    $result = $localization->setLocale();

    expect($result)->toBe('en')
        ->and($localization->getCurrentLocale())->toBe('en')
        ->and($localization->getCurrentLocaleRegional())->toBe('en_US');
});

it('caches locales after retrieval', function (): void {
    Locale::query()->whereIn('code', ['en', 'nl'])->update(['active' => true]);
    Cache::forget('site-locales');

    $localization = resolve(LocalizationService::class);

    $localization->getActiveLocales();

    $cacheValue = Cache::get('site-locales');
    expect($cacheValue)->toBeArray()
        ->and($cacheValue)->toHaveKeys(['en', 'nl']);
});
