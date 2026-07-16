<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Settings;
use App\Services\SettingsService;
use App\Services\UiStrings;
use Livewire\Livewire;

function activateTranslationLocale(string $code = 'nl'): void
{
    Locale::query()->where('code', $code)->update(['active' => true]);
    cache()->forget('site-locales');
}

it('translates interface strings for a locale from stored settings', function (): void {
    config()->set('site.ui_translations', ['nl' => ['Log in' => 'Inloggen', 'Blank' => '', 'Bad' => 123]]);
    app()->setLocale('nl');

    expect(__('Log in'))->toBe('Inloggen')
        ->and(__('Save'))->toBe('Save')
        ->and(__('Blank'))->toBe('Blank')
        ->and(__('Bad'))->toBe('Bad');
});

it('leaves strings untranslated when the locale has no overrides', function (): void {
    config()->set('site.ui_translations', ['fr' => ['Log in' => 'Connexion']]);
    app()->setLocale('nl');

    expect(__('Log in'))->toBe('Log in');
});

it('does not affect grouped translation lookups', function (): void {
    app()->setLocale('nl');

    expect(trans('a-missing-group.item'))->toBe('a-missing-group.item');
});

it('scans the visitor-facing views into a grouped, de-duplicated catalog', function (): void {
    $catalog = UiStrings::catalog();

    $groups = collect($catalog)->pluck('group');
    $strings = collect($catalog)->flatMap(fn (array $group): array => $group['strings']);

    expect($groups->all())->toBe(['Account', 'Sign in', 'Site'])
        ->and($strings)->toContain('My account')
        ->and($strings)->toContain('Log out')
        ->and($strings->count())->toBe($strings->unique()->count());
});

it('lists only non-english active locales for translation', function (): void {
    activateTranslationLocale('nl');

    expect(SettingsService::current()->interfaceTranslationLocales())->toBe(['nl']);
});

it('enables interface translations only with sign-ups on and a non-english locale', function (): void {
    expect(SettingsService::current()->showsInterfaceTranslations())->toBeFalse();

    config()->set('site.allow_registration', true);
    expect(SettingsService::current()->showsInterfaceTranslations())->toBeFalse();

    activateTranslationLocale('nl');
    expect(SettingsService::current()->showsInterfaceTranslations())->toBeTrue();

    config()->set('site.allow_registration', false);
    expect(SettingsService::current()->showsInterfaceTranslations())->toBeFalse();
});

it('returns 404 for the translations page when it does not apply', function (): void {
    $this->actingAsAdmin();

    $this->get(route('admin.settings-translations'))->assertNotFound();
});

it('shows and saves translations when applicable', function (): void {
    config()->set('site.allow_registration', true);
    activateTranslationLocale('nl');
    $this->actingAsAdmin();

    $this->get(route('admin.settings-translations'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-translations');

    Livewire::test('pages::admin.settings-translations')
        ->call('saveString', md5('Log in'), 'Inloggen')
        ->assertHasNoErrors();

    expect(Settings::get('ui_translations'))->toBe(['nl' => ['Log in' => 'Inloggen']]);
});

it('clears a translation when saved empty', function (): void {
    activateTranslationLocale('nl');
    Settings::set(['allow_registration' => true, 'ui_translations' => ['nl' => ['Log in' => 'Inloggen']]]);
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-translations')
        ->call('saveString', md5('Log in'), '')
        ->assertHasNoErrors();

    expect(Settings::get('ui_translations'))->toBe([]);
});

it('ignores an unknown string hash', function (): void {
    config()->set('site.allow_registration', true);
    activateTranslationLocale('nl');
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-translations')
        ->call('saveString', 'not-a-real-hash', 'Whatever')
        ->assertHasNoErrors();

    expect(Settings::get('ui_translations'))->toBeNull();
});

it('hydrates stored translations into the editor', function (): void {
    activateTranslationLocale('nl');
    Settings::set(['allow_registration' => true, 'ui_translations' => ['nl' => ['Log in' => 'Inloggen']]]);
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-translations')
        ->assertSet('translations.nl.'.md5('Log in'), 'Inloggen');
});

it('filters out and counts already-translated strings', function (): void {
    activateTranslationLocale('nl');
    Settings::set(['allow_registration' => true, 'ui_translations' => ['nl' => ['My account' => 'Mijn account']]]);
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-translations');

    expect($component->instance()->untranslatedCount)->toBe($component->instance()->totalCount - 1);

    $component->assertSee('My account')
        ->set('hideTranslated', true)
        ->assertDontSee('My account')
        ->assertSee('Log out');
});

it('shows the translations nav link only when applicable', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.sidebar-settings')->assertDontSee(__('Translations'));

    config()->set('site.allow_registration', true);
    activateTranslationLocale('nl');

    Livewire::test('admin.sidebar-settings')->assertSee(__('Translations'));
});
