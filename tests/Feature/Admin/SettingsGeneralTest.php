<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\User;
use Livewire\Livewire;

it('can render the general settings screen', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.settings-general'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-general');
});

it('redirects the settings index to general', function (): void {
    $this->actingAsAdmin()
        ->get('/admin/settings')
        ->assertRedirect('/admin/settings/general');
});

it('redirects authenticated non-admin users away from general settings', function (): void {
    $nonAdmin = User::factory()->create(['active' => true, 'admin' => false]);

    $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.settings-general'))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from general settings', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.settings-general'))
        ->assertRedirectToRoute('login');
});

it('hydrates the active languages on mount', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    $languages = Livewire::test('pages::admin.settings-general')->get('languages');

    expect($languages)->toContain('en')->toContain('nl');
});

it('persists the selected active languages', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('languages', ['en', 'nl'])
        ->call('update')
        ->assertHasNoErrors();

    expect(Locale::query()->where('code', 'nl')->value('active'))->toBeTrue()
        ->and(Locale::query()->where('code', 'fr')->value('active'))->toBeFalse();
});

it('keeps the default locale active even when it is deselected', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('languages', ['nl'])
        ->call('update')
        ->assertHasNoErrors()
        ->assertSet('languages', ['nl', 'en']);

    expect(Locale::query()->where('code', 'en')->value('active'))->toBeTrue();
});

it('requires at least one active language', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('languages', [])
        ->call('update')
        ->assertHasErrors(['languages']);
});

it('rejects an unknown locale code', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('languages', ['en', 'zz'])
        ->call('update')
        ->assertHasErrors(['languages.1']);
});

it('invalidates the site-locales cache so the active set reflects the change', function (): void {
    $this->actingAsAdmin();

    resolve('localization')->getActiveLocales();

    Livewire::test('pages::admin.settings-general')
        ->set('languages', ['en', 'nl'])
        ->call('update')
        ->assertHasNoErrors();

    expect(resolve('localization')->getActiveLocaleCodes()->all())->toContain('nl');
});
