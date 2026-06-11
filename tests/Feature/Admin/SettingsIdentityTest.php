<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Locale;
use App\Models\Media;
use App\Models\Settings;
use App\Models\User;
use Livewire\Livewire;

it('can render the identity settings screen', function (): void {
    $response = $this->actingAsAdmin()
        ->get(route('admin.settings-identity'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.settings-identity')
        ->assertSeeLivewire('media-selector');
});

it('redirects the settings index to identity', function (): void {
    $this->actingAsAdmin()
        ->get('/admin/settings')
        ->assertRedirect('/admin/settings/identity');
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

it('creates the singleton settings row on mount', function (): void {
    expect(Settings::query()->count())->toBe(0);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity');

    expect(Settings::query()->count())->toBe(1);
});

it('populates the form with existing translations on mount', function (): void {
    $settings = Settings::current();
    $settings->update(['title' => ['en' => 'Acme Inc'], 'description' => ['en' => 'We build things']]);

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

it('hydrates the favicon from existing media on mount', function (): void {
    $settings = Settings::current();
    $favicon = Media::factory()->create(['type' => MediaType::IMAGE]);
    $settings->syncMediaForRole('favicon', resolve('localization')->getDefaultLocale(), [['id' => $favicon->id]]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->assertSet('favicon.id', $favicon->id);
});

it('persists title and tagline translations on update', function (): void {
    $settings = Settings::current();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->set('title.en', 'New Site Title')
        ->set('description.en', 'A fresh tagline')
        ->call('update')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings-identity'));

    $this->assertDatabaseHas('translations', [
        'translatable_id' => $settings->id,
        'translatable_type' => 'settings',
        'locale' => 'en',
        'key' => 'title',
        'body' => 'New Site Title',
    ]);

    $this->assertDatabaseHas('translations', [
        'translatable_id' => $settings->id,
        'translatable_type' => 'settings',
        'locale' => 'en',
        'key' => 'description',
        'body' => 'A fresh tagline',
    ]);
});

it('attaches a favicon to the settings row on update', function (): void {
    $settings = Settings::current();
    $favicon = Media::factory()->create(['type' => MediaType::IMAGE]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->set('title.en', 'Branded Site')
        ->set('favicon', ['id' => $favicon->id])
        ->call('update')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mediables', [
        'media_id' => $favicon->id,
        'mediable_id' => $settings->id,
        'mediable_type' => 'settings',
        'role' => 'favicon',
        'locale' => resolve('localization')->getDefaultLocale(),
    ]);
});

it('validates that the title is required', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-identity')
        ->set('title.en', '')
        ->call('update')
        ->assertHasErrors(['title.en' => 'required']);
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

it('builds the favicon url from the storage key so it resolves through the image pipeline', function (): void {
    $settings = Settings::current();
    $favicon = Media::factory()->create(['type' => MediaType::IMAGE]);

    $settings->syncMediaForRole('favicon', resolve('localization')->getDefaultLocale(), [['id' => $favicon->id]]);

    $url = $settings->fresh()->faviconUrl();

    expect($url)
        ->toBeString()
        ->toContain('/img/')
        ->toContain($favicon->source);
});
