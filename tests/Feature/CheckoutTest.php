<?php

declare(strict_types=1);

use App\Actions\CreateCheckoutAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Record;
use App\Models\Settings;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    connectStripe();
});

function fakeCheckoutSession(string $url = 'https://checkout.stripe.com/c/pay/cs_test_new'): void
{
    stripe()->respond('POST', '/v1/checkout/sessions', ['id' => 'cs_test_new', 'object' => 'checkout.session', 'url' => $url]);
}

it('opens stripe checkout for the cart and reserves the stock', function (): void {
    fakeCheckoutSession();
    $record = sellableRecord(['current_price' => '12.50', 'heading' => ['en' => 'Walnut lamp'], 'sku' => 'LAMP-1'], ['stock' => 5]);
    resolve(CartService::class)->add($record, 2);

    Livewire::test('pages::cart')
        ->call('checkout')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_new');

    $order = Order::query()->sole();

    expect($order->status)->toBe(OrderStatus::PENDING)
        ->and($order->stripe_session_id)->toBe('cs_test_new')
        ->and($order->subtotal_amount)->toBe(2500)
        ->and($order->items->sole()->only(['name', 'sku', 'quantity', 'unit_amount', 'reserved_stock']))
        ->toBe(['name' => 'Walnut lamp', 'sku' => 'LAMP-1', 'quantity' => 2, 'unit_amount' => 1250, 'reserved_stock' => 2])
        ->and($record->refresh()->stock)->toBe(3)
        ->and(session(CreateCheckoutAction::LAST_ORDER_KEY))->toBe($order->id);

    stripe()->assertSent('POST', '/v1/checkout/sessions', fn (array $params): bool => $params['mode'] === 'payment'
        && $params['line_items'][0]['quantity'] === 2
        && $params['line_items'][0]['price_data']['unit_amount'] === 1250
        && $params['line_items'][0]['price_data']['currency'] === 'usd'
        && ! isset($params['line_items'][0]['price_data']['tax_behavior'])
        && str_ends_with($params['success_url'], '?session_id={CHECKOUT_SESSION_ID}')
        && $params['metadata']['order_id'] === (string) $order->id
        && str_ends_with($params['cancel_url'], '?cancelled='.$order->reference)
        && ! isset($params['shipping_address_collection'], $params['automatic_tax'], $params['customer_email']));
});

it('fills in the email of a signed-in shopper', function (): void {
    fakeCheckoutSession();
    $user = User::factory()->member()->create(['email' => 'member@example.com']);
    resolve(CartService::class)->add(sellableRecord(), 1);

    $this->actingAs($user);

    Livewire::test('pages::cart')->call('checkout');

    expect(Order::query()->sole()->user_id)->toBe($user->id);
    stripe()->assertSent('POST', '/v1/checkout/sessions', fn (array $params): bool => $params['customer_email'] === 'member@example.com');
});

it('asks for a shipping address and offers the shipping rates for items that ship', function (): void {
    fakeCheckoutSession();
    Settings::set([
        'shop_shipping_countries' => ['CA', 'US'],
        'shop_shipping_rates' => [['name' => 'Standard delivery', 'amount' => '5.00']],
        'shop_automatic_tax' => true,
        'shop_prices_include_tax' => true,
    ]);
    resolve(CartService::class)->add(sellableRecord(['current_price' => '20', 'shippable' => true]), 1);

    Livewire::test('pages::cart')->call('checkout')->assertHasNoErrors();

    stripe()->assertSent('POST', '/v1/checkout/sessions', fn (array $params): bool => $params['shipping_address_collection']['allowed_countries'] === ['CA', 'US']
        && $params['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] === 500
        && $params['shipping_options'][0]['shipping_rate_data']['tax_behavior'] === 'inclusive'
        && $params['line_items'][0]['price_data']['tax_behavior'] === 'inclusive'
        && $params['automatic_tax'] === ['enabled' => 'true']);
});

it('holds checkout back until shipping countries are set for items that ship', function (): void {
    resolve(CartService::class)->add(sellableRecord(['current_price' => '20', 'shippable' => true]), 1);

    Livewire::test('pages::cart')
        ->call('checkout')
        ->assertHasErrors(['checkout' => __('Checkout is not available yet. Please try again later.')]);

    expect(Order::query()->count())->toBe(0);
    stripe()->assertNothingSent();
});

it('refuses checkout when the last items sell out first', function (): void {
    $record = sellableRecord(['current_price' => '10', 'heading' => ['en' => 'Lamp']], ['stock' => 2]);
    $other = sellableRecord(['current_price' => '5'], ['stock' => 4]);
    resolve(CartService::class)->add($other, 1);
    resolve(CartService::class)->add($record, 2);
    Order::creating(fn (): int => Record::query()->whereKey($record->id)->update(['stock' => 1]));

    Livewire::test('pages::cart')
        ->call('checkout')
        ->assertHasErrors('checkout')
        ->assertSee(__(':item just sold out, so your cart has been updated.', ['item' => 'Lamp']));

    expect(Order::query()->count())->toBe(0)
        ->and($other->refresh()->stock)->toBe(4);

    stripe()->assertNothingSent();
});

it('puts the stock back and apologises when stripe cannot open checkout', function (): void {
    stripe()->fail('POST', '/v1/checkout/sessions', 'Stripe Tax is not set up', 400);
    $record = sellableRecord(attributes: ['stock' => 3]);
    resolve(CartService::class)->add($record, 2);

    Livewire::test('pages::cart')
        ->call('checkout')
        ->assertHasErrors(['checkout' => __('Checkout is not available right now. Please try again later.')]);

    expect($record->refresh()->stock)->toBe(3)
        ->and(Order::query()->sole()->status)->toBe(OrderStatus::CANCELLED);
});

it('will not open checkout for an empty cart', function (): void {
    expect(fn (): string => resolve(CreateCheckoutAction::class)->handle(null))
        ->toThrow(ValidationException::class);

    stripe()->assertNothingSent();
});
