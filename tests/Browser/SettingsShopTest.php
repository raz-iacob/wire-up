<?php

declare(strict_types=1);

use App\Models\Settings;

it('saves the countries picked in the ship-to box', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-shop'));

    $page->assertNoJavascriptErrors()
        ->click('Choose countries…')
        ->click('France')
        ->click('Germany')
        ->keys('ui-pillbox input[type="text"]', 'Escape')
        ->press('Update')
        ->assertSee('Shop settings have been updated.')
        ->assertNoJavascriptErrors();

    expect(Settings::get('shop_shipping_countries'))->toBe(['FR', 'DE']);
});
