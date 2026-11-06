<?php

declare(strict_types=1);

use App\Models\Settings;
use Livewire\Livewire;

it('replaces a saved secret with a newly typed one and clears the field again', function (): void {
    Settings::set(['pexels_api_key' => 'saved-pexels']);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.pexels')
        ->set('form.pexels_api_key', '  new-pexels  ')
        ->call('connect')
        ->assertHasNoErrors()
        ->assertSet('form.pexels_api_key', '');

    expect(Settings::get('pexels_api_key'))->toBe('new-pexels');
});

it('connects pexels by persisting the api key', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.pexels')
        ->set('form.pexels_api_key', 'new-pexels-key')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('pexels_api_key'))->toBe('new-pexels-key');
});

it('requires an api key to connect pexels', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.pexels')
        ->set('form.pexels_api_key', '')
        ->call('connect')
        ->assertHasErrors(['form.pexels_api_key']);
});
