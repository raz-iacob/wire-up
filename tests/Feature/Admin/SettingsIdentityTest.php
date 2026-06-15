<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Media;
use App\Models\Settings;
use App\Models\User;
use App\Services\SettingsService;
use Livewire\Livewire;

it('can render the identity settings screen', function (): void {
    $response = $this->actingAsAdmin()
        ->get(route('admin.settings-identity'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.settings-identity')
        ->assertSeeLivewire('admin.media-selector')
        ->assertSee('fonts.bunny.net/css?family=inter', false);
});

it('redirects authenticated non-admin users away from identity settings', function (): void {
    $nonAdmin = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.settings-identity'))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from identity settings', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.settings-identity'))
        ->assertRedirectToRoute('login');
});

it('starts with empty identity fields when nothing is saved', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->assertSet('title', [])
        ->assertSet('description', [])
        ->assertSet('favicon', null);
});

it('populates the form with existing values on mount', function (): void {
    Settings::set(['title' => ['en' => 'Acme Inc'], 'description' => ['en' => 'We build things']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->assertSet('title.en', 'Acme Inc')
        ->assertSet('description.en', 'We build things');
});

it('cycles to the next active locale on the change-locale event', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->assertSet('locale', 'en')
        ->call('changeLocale')
        ->assertSet('locale', 'nl')
        ->call('changeLocale')
        ->assertSet('locale', 'en');
});

it('hydrates the favicon from the saved item on mount', function (): void {
    Settings::set(['favicon' => ['id' => 42, 'source' => 'images/favicon.png']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->assertSet('favicon.id', 42);
});

it('persists title and tagline on update', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->set('title.en', 'New Site Title')
        ->set('description.en', 'A fresh tagline')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('title'))->toBe(['en' => 'New Site Title'])
        ->and(Settings::get('description'))->toBe(['en' => 'A fresh tagline']);
});

it('stores the favicon item on update', function (): void {
    $favicon = Media::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->set('title.en', 'Branded Site')
        ->set('favicon', ['id' => $favicon->id, 'source' => $favicon->source])
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('favicon')['id'])->toBe($favicon->id);
});

it('validates that the title is required', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->set('title.en', '')
        ->call('update')
        ->assertHasErrors(['title.en' => 'required']);
});

it('shows a friendly title error instead of the raw field path', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->set('title.en', '')
        ->call('update')
        ->assertHasErrors(['title.en'])
        ->assertSee('Enter a title for the selected language.')
        ->assertDontSee('title.en field is required');
});

it('switches the editing locale to the one carrying a validation error', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->assertSet('locale', 'en')
        ->set('title.en', 'Valid Title')
        ->call('update')
        ->assertHasErrors(['title.nl' => 'required'])
        ->assertSet('locale', 'nl');
});

it('validates the title maximum length', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->set('title.en', str_repeat('a', 121))
        ->call('update')
        ->assertHasErrors(['title.en' => 'max']);
});

it('rejects a favicon referencing a non-existent media id', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->set('title.en', 'Valid Title')
        ->set('favicon', ['id' => 999999])
        ->call('update')
        ->assertHasErrors(['favicon.id']);
});

it('builds the favicon url from the saved item so it resolves through the image pipeline', function (): void {
    $favicon = Media::factory()->create();

    Settings::set(['favicon' => ['id' => $favicon->id, 'source' => $favicon->source]]);

    $url = (new SettingsService)->faviconUrl();

    expect($url)
        ->toBeString()
        ->toContain('/img/')
        ->toContain($favicon->source);
});
