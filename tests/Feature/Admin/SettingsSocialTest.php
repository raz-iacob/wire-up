<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Models\User;
use Livewire\Livewire;

it('can render the social settings screen', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.settings-social'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-social');
});

it('redirects authenticated non-admin users away from social settings', function (): void {
    $nonAdmin = User::factory()->create(['active' => true, 'role' => 'member']);

    $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.settings-social'))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from social settings', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.settings-social'))
        ->assertRedirectToRoute('login');
});

it('starts with empty links for every configured platform', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-social')
        ->assertSet('links.facebook', '')
        ->assertSet('links.x', '')
        ->assertSet('links.tiktok', '');
});

it('hydrates social links from metadata on mount', function (): void {
    Settings::set(['social' => ['facebook' => 'https://facebook.com/acme']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-social')
        ->assertSet('links.facebook', 'https://facebook.com/acme')
        ->assertSet('links.instagram', '');
});

it('persists the provided social links to metadata', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-social')
        ->set('links.facebook', 'https://facebook.com/acme')
        ->set('links.youtube', 'https://youtube.com/@acme')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('social'))
        ->toBe(['facebook' => 'https://facebook.com/acme', 'youtube' => 'https://youtube.com/@acme']);
});

it('stores only the non-empty links', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-social')
        ->set('links.facebook', 'https://facebook.com/acme')
        ->call('update')
        ->assertHasNoErrors();

    $social = Settings::get('social');

    expect($social)->toHaveKey('facebook')
        ->and($social)->not->toHaveKey('instagram');
});

it('validates that a social link is a valid url', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-social')
        ->set('links.facebook', 'not-a-url')
        ->call('update')
        ->assertHasErrors(['links.facebook']);
});

it('defaults the icon variant to solid', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-social')
        ->assertSet('variant', 'solid');
});

it('persists the chosen icon variant', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-social')
        ->set('variant', 'outline')
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('social_icon_variant'))->toBe('outline');
});

it('validates the icon variant is a known option', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-social')
        ->set('variant', 'bogus')
        ->call('update')
        ->assertHasErrors(['variant']);
});
