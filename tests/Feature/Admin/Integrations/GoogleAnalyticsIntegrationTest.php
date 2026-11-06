<?php

declare(strict_types=1);

use App\Models\Settings;
use Livewire\Livewire;

it('hydrates the saved measurement and property ids on mount', function (): void {
    Settings::set([
        'google_analytics_id' => 'G-SAVED01',
        'google_analytics_property_id' => '987654321',
    ]);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.google-analytics')
        ->assertSet('form.google_analytics_id', 'G-SAVED01')
        ->assertSet('form.google_analytics_property_id', '987654321');
});

it('drops the saved analytics key file when the property id is cleared', function (): void {
    Settings::set(['google_analytics_id' => 'G-SAVED01', 'google_analytics_property_id' => '987654321', 'google_analytics_credentials' => '{"client_email":"a@b.c","private_key":"k"}']);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.google-analytics')
        ->set('form.google_analytics_property_id', '')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('google_analytics_credentials'))->toBe('');
});

it('connects google analytics by persisting the measurement id', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.google-analytics')
        ->set('form.google_analytics_id', 'G-NEW0001')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('google_analytics_id'))->toBe('G-NEW0001')
        ->and(Settings::get('google_analytics_property_id'))->toBe('')
        ->and(Settings::get('google_analytics_credentials'))->toBe('');
});

it('connects google analytics reports by persisting the property id and credentials', function (): void {
    $this->actingAsAdmin();

    $credentials = '{"client_email":"reports@example.com","private_key":"a-key"}';

    Livewire::test('admin.integrations.google-analytics')
        ->set('form.google_analytics_id', 'G-NEW0001')
        ->set('form.google_analytics_property_id', '123456789')
        ->set('form.google_analytics_credentials', $credentials)
        ->call('connect')
        ->assertHasNoErrors()
        ->assertDispatched('integrations-updated');

    expect(Settings::get('google_analytics_id'))->toBe('G-NEW0001')
        ->and(Settings::get('google_analytics_property_id'))->toBe('123456789')
        ->and(Settings::get('google_analytics_credentials'))->toBe($credentials);
});

it('requires the analytics reports fields together', function (string $field, string $value, string $missing): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.google-analytics')
        ->set('form.google_analytics_id', 'G-NEW0001')
        ->set($field, $value)
        ->call('connect')
        ->assertHasErrors([$missing => 'required_with']);
})->with([
    'property id without credentials' => ['form.google_analytics_property_id', '123456789', 'form.google_analytics_credentials'],
    'credentials without property id' => ['form.google_analytics_credentials', '{"client_email":"a@b.c","private_key":"k"}', 'form.google_analytics_property_id'],
]);

it('rejects a non-numeric property id', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.google-analytics')
        ->set('form.google_analytics_id', 'G-NEW0001')
        ->set('form.google_analytics_property_id', 'G-NEW0001')
        ->set('form.google_analytics_credentials', '{"client_email":"a@b.c","private_key":"k"}')
        ->call('connect')
        ->assertHasErrors(['form.google_analytics_property_id'])
        ->assertSee('Enter the numeric GA4 property ID, like 123456789.');
});

it('rejects credentials that are not a valid service account key', function (string $credentials): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.google-analytics')
        ->set('form.google_analytics_id', 'G-NEW0001')
        ->set('form.google_analytics_property_id', '123456789')
        ->set('form.google_analytics_credentials', $credentials)
        ->call('connect')
        ->assertHasErrors(['form.google_analytics_credentials'])
        ->assertSee('Paste the full service account JSON key file.');
})->with([
    'not json' => ['not-json'],
    'missing client email' => ['{"private_key":"k"}'],
    'missing private key' => ['{"client_email":"a@b.c"}'],
    'empty client email' => ['{"client_email":"","private_key":"k"}'],
]);

it('validates the google analytics id format', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.google-analytics')
        ->set('form.google_analytics_id', 'UA-12345')
        ->call('connect')
        ->assertHasErrors(['form.google_analytics_id'])
        ->assertSee('Enter a valid Google Analytics measurement ID, like G-XXXXXXXXXX.');
});
