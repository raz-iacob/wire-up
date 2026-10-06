<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Models\User;
use App\Services\ShopService;
use Livewire\Livewire;

it('renders the shop settings for admins and lists them in the sidebar once stripe is connected', function (): void {
    connectStripe();

    $this->actingAsAdmin()
        ->get(route('admin.settings-shop'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-shop')
        ->assertSee(route('admin.settings-shop'));
});

it('saves the shipping countries, rates and tax options', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-shop')
        ->set('countries', ['CA', 'US'])
        ->call('addRate')
        ->set('rates.0.name', '  Standard delivery ')
        ->set('rates.0.amount', '5.50')
        ->set('automaticTax', true)
        ->set('pricesIncludeTax', true)
        ->call('update')
        ->assertHasNoErrors();

    $shop = resolve(ShopService::class);

    expect($shop->shippingCountries())->toBe(['CA', 'US'])
        ->and($shop->shippingRates())->toBe([['name' => 'Standard delivery', 'amount' => '5.50']])
        ->and($shop->calculatesTax())->toBeTrue()
        ->and($shop->pricesIncludeTax())->toBeTrue();
});

it('loads the saved shop settings', function (): void {
    Settings::set([
        'shop_shipping_countries' => ['GB'],
        'shop_shipping_rates' => [['name' => 'Royal Mail', 'amount' => '3']],
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-shop')
        ->assertSet('countries', ['GB'])
        ->assertSet('rates', [['name' => 'Royal Mail', 'amount' => '3']]);
});

it('rejects unknown countries and incomplete rates', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-shop')
        ->set('countries', ['XX'])
        ->set('rates', [['name' => '', 'amount' => '-1']])
        ->call('update')
        ->assertHasErrors(['countries.0' => 'in', 'rates.0.name' => 'required', 'rates.0.amount' => 'min']);
});

it('offers at most five shipping rates and removes them', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-shop');

    foreach (range(1, 7) as $attempt) {
        $component->call('addRate');
    }

    $component->assertCount('rates', ShopService::MAX_SHIPPING_RATES)
        ->call('removeRate', 0)
        ->assertCount('rates', ShopService::MAX_SHIPPING_RATES - 1);
});

it('forbids changing the shop settings without the settings edit ability', function (): void {
    $this->actingAs(User::factory()->editor()->create(['active' => true]));

    Livewire::test('pages::admin.settings-shop')
        ->call('update')
        ->assertForbidden();
});

it('ignores saved shop values that are no longer valid', function (): void {
    Settings::set([
        'shop_shipping_countries' => ['CA', 'XX', 7],
        'shop_shipping_rates' => [['name' => 'Ok', 'amount' => '1'], ['name' => '', 'amount' => '2'], 'junk', ...array_fill(0, 6, ['name' => 'Extra', 'amount' => '1'])],
    ]);

    $shop = resolve(ShopService::class);

    expect($shop->shippingCountries())->toBe(['CA'])
        ->and($shop->shippingRates())->toHaveCount(ShopService::MAX_SHIPPING_RATES)
        ->and($shop->shippingRates()[0])->toBe(['name' => 'Ok', 'amount' => '1']);
});

it('formats an order amount in its own currency', function (): void {
    expect(resolve(ShopService::class)->formatMinor(1500, 'JPY'))->toBe(config('currencies.JPY.symbol').'1,500');
});
