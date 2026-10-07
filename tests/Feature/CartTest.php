<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Services\CartService;
use App\Services\SettingsService;
use App\Services\ShopService;
use App\Services\UiStrings;
use Livewire\Livewire;

beforeEach(function (): void {
    connectStripe();
});

function cart(): CartService
{
    return resolve(CartService::class);
}

it('adds an item and merges repeat additions', function (): void {
    $record = sellableRecord(['current_price' => '12.50']);

    expect(cart()->add($record, 2))->toBeTrue()
        ->and(cart()->add($record, 1))->toBeTrue()
        ->and(cart()->count())->toBe(3)
        ->and(cart()->subtotal())->toBe(3750);
});

it('caps the quantity at the stock left and at the cart maximum', function (): void {
    $limited = sellableRecord(attributes: ['stock' => 3]);
    $plenty = sellableRecord();

    cart()->add($limited, 10);
    cart()->add($plenty, 500);

    expect(array_column(cart()->lines(), 'quantity'))->toBe([3, CartService::MAX_QUANTITY]);
});

it('refuses items that cannot be bought or are subscriptions', function (Closure $record): void {
    expect(cart()->add($record(), 1))->toBeFalse()
        ->and(cart()->count())->toBe(0);
})->with([
    'sold out' => [fn (): Record => sellableRecord(attributes: ['stock' => 0])],
    'not for sale' => [fn (): Record => sellableRecord(sellable: false)],
    'subscription' => [fn (): Record => sellableRecord(['current_price' => '9', 'billing' => 'Monthly'])],
]);

it('changes, caps and removes quantities', function (): void {
    $record = sellableRecord(attributes: ['stock' => 5]);
    cart()->add($record, 1);

    cart()->update($record->id, 4);
    expect(cart()->count())->toBe(4);

    cart()->update($record->id, 50);
    expect(cart()->count())->toBe(5);

    cart()->update($record->id, 0);
    expect(cart()->lines())->toBe([]);
});

it('ignores changes to items that are not in the cart', function (): void {
    $record = sellableRecord();

    cart()->update($record->id, 3);

    expect(session(CartService::SESSION_KEY))->toBeNull();
});

it('drops an item that stopped being for sale when its quantity changes', function (): void {
    $record = sellableRecord();
    cart()->add($record, 1);
    $record->update(['status' => ContentStatus::DRAFT]);

    cart()->update($record->id, 2);

    expect(session(CartService::SESSION_KEY))->toBe([]);
});

it('prunes items that are gone or no longer for sale when the cart is read', function (): void {
    $kept = sellableRecord();
    $deleted = sellableRecord();
    $unpublished = sellableRecord();
    cart()->add($kept, 1);
    cart()->add($deleted, 1);
    cart()->add($unpublished, 1);

    $deleted->delete();
    $unpublished->update(['status' => ContentStatus::DRAFT]);

    expect(cart()->lines())->toHaveCount(1)
        ->and(session(CartService::SESSION_KEY))->toBe([$kept->id => 1]);
});

it('removes and clears items', function (): void {
    $first = sellableRecord();
    $second = sellableRecord();
    cart()->add($first, 1);
    cart()->add($second, 1);

    cart()->remove($first->id);
    expect(cart()->count())->toBe(1);

    cart()->clear();
    expect(session()->has(CartService::SESSION_KEY))->toBeFalse();
});

it('tolerates a tampered session', function (mixed $stored): void {
    session([CartService::SESSION_KEY => $stored]);

    expect(cart()->lines())->toBe([]);
})->with([
    'not a list' => ['nonsense'],
    'bad entries' => [['x' => 1, 5 => 'two', 6 => -1]],
]);

it('converts and formats amounts in the smallest currency unit', function (): void {
    $shop = resolve(ShopService::class);

    expect($shop->toMinor('19.99'))->toBe(1999)
        ->and($shop->formatMinor(1999))->toBe(SettingsService::current()->formatMoney(19.99));
});

it('opens the shop only once stripe is connected and a type sells', function (): void {
    expect(resolve(ShopService::class)->isOpen())->toBeFalse();

    sellableRecord();

    expect(resolve(ShopService::class)->isOpen())->toBeTrue();

    config(['cashier.secret' => null]);

    expect(resolve(ShopService::class)->isOpen())->toBeFalse();
});

it('adds the chosen quantity from the buy box', function (): void {
    $record = sellableRecord();

    Livewire::test('site.buy-box', ['record' => $record])
        ->set('quantity', 2)
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('quantity', 1)
        ->assertDispatched('cart-updated');

    expect(cart()->count())->toBe(2);
});

it('tells the shopper how many are left', function (): void {
    $record = sellableRecord(attributes: ['stock' => 2]);

    Livewire::test('site.buy-box', ['record' => $record])
        ->set('quantity', 3)
        ->call('add')
        ->assertHasErrors('quantity')
        ->assertSee(__('Only :count left in stock.', ['count' => 2]));

    expect(cart()->count())->toBe(0);
});

it('explains when the item sold out while the page was open', function (): void {
    $record = sellableRecord(attributes: ['stock' => 2]);

    $component = Livewire::test('site.buy-box', ['record' => $record]);

    Record::query()->whereKey($record->id)->update(['stock' => 0]);

    $component->call('add')
        ->assertHasErrors('quantity')
        ->assertSee(__('This item is no longer available.'))
        ->assertNotDispatched('cart-updated');
});

it('shows the cart count in the header once the shop is open', function (): void {
    $record = sellableRecord();
    cart()->add($record, 3);

    Livewire::test('site.cart-icon')
        ->assertSee(route('cart'))
        ->assertSee(__('Cart (:count)', ['count' => 3]))
        ->dispatch('cart-updated')
        ->assertOk();
});

it('hides the cart icon while the shop is closed', function (): void {
    config(['cashier.secret' => null]);

    Livewire::test('site.cart-icon')->assertDontSee(route('cart'));
});

it('lists the cart with its subtotal', function (): void {
    $record = sellableRecord(['current_price' => '10', 'heading' => ['en' => 'Lamp']]);
    cart()->add($record, 2);

    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('Lamp')
        ->assertSee($record->getUrl())
        ->assertSee(resolve(ShopService::class)->formatMinor(2000));
});

it('lists an item without a detail page as plain text', function (): void {
    $record = sellableRecord(['current_price' => '10', 'heading' => ['en' => 'Gift card']]);
    $record->recordType->update(['has_detail_page' => false]);
    cart()->add($record, 1);

    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('Gift card')
        ->assertDontSee($record->getUrl());
});

it('shows an empty cart', function (): void {
    $this->get(route('cart'))
        ->assertOk()
        ->assertSee(__('Your cart is empty.'));
});

it('changes quantities and removes items on the cart page', function (): void {
    $record = sellableRecord();
    cart()->add($record, 1);

    Livewire::test('pages::cart')
        ->call('changeQuantity', $record->id, 3)
        ->assertDispatched('cart-updated');

    expect(cart()->count())->toBe(3);

    Livewire::test('pages::cart')
        ->call('remove', $record->id)
        ->assertSee(__('Your cart is empty.'));
});

it('offers the buy box on a product page and on other sellable record pages', function (string $presetKey): void {
    $record = sellableRecord(['current_price' => '15']);
    $record->recordType->update(['key' => $presetKey]);

    $this->get($record->refresh()->getUrl())
        ->assertOk()
        ->assertSeeLivewire('site.buy-box');
})->with(['product', 'workshop']);

it('leaves the buy box off when the record cannot be bought online', function (Closure $record): void {
    $this->get($record()->getUrl())
        ->assertOk()
        ->assertDontSeeLivewire('site.buy-box');
})->with([
    'sold out' => [fn (): Record => sellableRecord(attributes: ['stock' => 0])],
    'stripe not connected' => [function (): Record {
        config(['cashier.secret' => null]);

        return sellableRecord();
    }],
]);

it('lists the cart wording among the editable interface strings', function (): void {
    cache()->forget('ui-strings-catalog');

    expect(collect(UiStrings::catalog())->firstWhere('group', 'Cart')['strings'] ?? [])->toContain('Your cart is empty.');
});
