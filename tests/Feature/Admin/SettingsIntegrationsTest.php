<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Livewire\Livewire;

it('can render the integrations settings screen', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.settings-integrations'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-integrations');
});

it('redirects authenticated non-admin users away from integrations settings', function (): void {
    $nonAdmin = User::factory()->create(['active' => true, 'role' => 'member']);

    $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.settings-integrations'))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from integrations settings', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.settings-integrations'))
        ->assertRedirectToRoute('login');
});

it('hydrates the saved credentials and custom code on mount', function (): void {
    Settings::set([
        'pexels_api_key' => 'saved-pexels-key',
        'google_analytics_id' => 'G-SAVED01',
        'google_maps_api_key' => 'saved-maps-key',
        'head_scripts' => '<script>head()</script>',
        'body_scripts' => '<script>body()</script>',
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->assertSet('pexels_api_key', 'saved-pexels-key')
        ->assertSet('google_analytics_id', 'G-SAVED01')
        ->assertSet('google_maps_api_key', 'saved-maps-key')
        ->assertSet('head_scripts', '<script>head()</script>')
        ->assertSet('body_scripts', '<script>body()</script>');
});

it('persists and trims the custom head and body code on update', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('head_scripts', "  <script>fbq('init')</script>  ")
        ->set('body_scripts', '  <script>chat()</script>  ')
        ->call('updateCustomCode')
        ->assertHasNoErrors();

    expect(Settings::get('head_scripts'))->toBe("<script>fbq('init')</script>")
        ->and(Settings::get('body_scripts'))->toBe('<script>chat()</script>');
});

it('connects pexels by persisting the api key', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('pexels_api_key', 'new-pexels-key')
        ->call('connectPexels')
        ->assertHasNoErrors();

    expect(Settings::get('pexels_api_key'))->toBe('new-pexels-key');
});

it('connects google analytics by persisting the measurement id', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_analytics_id', 'G-NEW0001')
        ->call('connectGoogleAnalytics')
        ->assertHasNoErrors();

    expect(Settings::get('google_analytics_id'))->toBe('G-NEW0001');
});

it('connects google maps by persisting the api key', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_maps_api_key', 'AIzaMapsKey123')
        ->call('connectGoogleMaps')
        ->assertHasNoErrors();

    expect(Settings::get('google_maps_api_key'))->toBe('AIzaMapsKey123');
});

it('requires an api key to connect google maps', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_maps_api_key', '')
        ->call('connectGoogleMaps')
        ->assertHasErrors(['google_maps_api_key']);
});

it('requires an api key to connect pexels', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('pexels_api_key', '')
        ->call('connectPexels')
        ->assertHasErrors(['pexels_api_key']);
});

it('disconnects an integration by clearing its credential', function (): void {
    Settings::set([
        'pexels_api_key' => 'existing',
        'google_analytics_id' => 'G-EXIST01',
        'google_maps_api_key' => 'existing-maps',
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->call('disconnect', 'pexels')
        ->assertHasNoErrors()
        ->assertSet('pexels_api_key', '')
        ->call('disconnect', 'google-analytics')
        ->assertSet('google_analytics_id', '')
        ->call('disconnect', 'google-maps')
        ->assertSet('google_maps_api_key', '');

    expect(Settings::get('pexels_api_key'))->toBe('')
        ->and(Settings::get('google_analytics_id'))->toBe('')
        ->and(Settings::get('google_maps_api_key'))->toBe('');
});

it('validates the google analytics id format', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('google_analytics_id', 'UA-12345')
        ->call('connectGoogleAnalytics')
        ->assertHasErrors(['google_analytics_id'])
        ->assertSee('Enter a valid Google Analytics measurement ID, like G-XXXXXXXXXX.');
});

it('bridges the saved pexels key into the services config at boot', function (): void {
    Settings::set(['pexels_api_key' => 'db-pexels-key']);

    new AppServiceProvider(app())->boot();

    expect(config('services.pexels.key'))->toBe('db-pexels-key');
});

it('falls back to the env pexels key when none is saved', function (): void {
    Settings::set(['pexels_api_key' => '']);
    config()->set('services.pexels.key', 'env-pexels-key');

    new AppServiceProvider(app())->boot();

    expect(config('services.pexels.key'))->toBe('env-pexels-key');
});

it('injects the custom head and body code into the public site', function (): void {
    Settings::set([
        'head_scripts' => '<script>window.headMarker=1</script>',
        'body_scripts' => '<script>window.bodyMarker=1</script>',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<script>window.headMarker=1</script>', false)
        ->assertSee('<script>window.bodyMarker=1</script>', false);
});

it('omits the custom code wrappers when nothing is configured', function (): void {
    Settings::set(['head_scripts' => '', 'body_scripts' => '']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('window.headMarker', false)
        ->assertDontSee('window.bodyMarker', false);
});
