<?php

declare(strict_types=1);

use App\Models\Settings;
use Livewire\Livewire;

it('connects google maps by persisting the api key', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.google-maps')
        ->set('form.google_maps_api_key', 'AIzaMapsKey123')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('google_maps_api_key'))->toBe('AIzaMapsKey123');
});

it('requires an api key to connect google maps', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.google-maps')
        ->set('form.google_maps_api_key', '')
        ->call('connect')
        ->assertHasErrors(['form.google_maps_api_key']);
});
