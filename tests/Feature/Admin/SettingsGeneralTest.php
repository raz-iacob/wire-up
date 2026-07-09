<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Settings;
use App\Models\User;
use App\Services\SettingsService;
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
    $nonAdmin = User::factory()->create(['active' => true, 'role' => 'member']);

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

it('persists the sign-up toggle', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('allow_registration', true)
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('allow_registration'))->toBeTrue();
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

it('hydrates the communication email on mount', function (): void {
    Settings::set(['contact_email' => 'owner@example.com']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->assertSet('contact_email', 'owner@example.com');
});

it('persists the communication email on update', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('contact_email', 'hello@example.com')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('contact_email'))->toBe('hello@example.com');
});

it('validates that the communication email is a valid email address', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('contact_email', 'not-an-email')
        ->call('update')
        ->assertHasErrors(['contact_email'])
        ->assertSee('Enter a valid communication email address.');
});

it('hydrates the deduced currency on mount', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->assertSet('currency', 'USD');
});

it('persists the chosen currency', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('currency', 'EUR')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('currency'))->toBe('EUR');
});

it('rejects an unknown currency', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('currency', 'ZZZ')
        ->call('update')
        ->assertHasErrors(['currency']);
});

it('defaults the homepage to the seeded home page on mount', function (): void {
    $this->actingAsAdmin();

    $homeId = SettingsService::current()->homePageId();

    Livewire::test('pages::admin.settings-general')
        ->assertSet('home_page_id', $homeId);
});

it('persists the chosen homepage', function (): void {
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('home_page_id', $page->id)
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('home_page_id'))->toBe($page->id);
});

it('validates the homepage references an existing page', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->set('home_page_id', 999999)
        ->call('update')
        ->assertHasErrors(['home_page_id']);
});
