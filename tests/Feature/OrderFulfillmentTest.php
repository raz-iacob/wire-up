<?php

declare(strict_types=1);

use App\Actions\FulfillCheckoutAction;
use App\Actions\RefundOrderAction;
use App\Actions\ReleaseOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Record;

beforeEach(function (): void {
    connectStripe();
});

function pendingOrder(?Record $record = null, int $quantity = 2, int $reserved = 0, array $attributes = []): Order
{
    $order = Order::factory()->create(['stripe_session_id' => 'cs_test_1', ...$attributes]);

    OrderItem::factory()->for($order)->create([
        'record_id' => $record?->id,
        'quantity' => $quantity,
        'reserved_stock' => $reserved,
    ]);

    return $order;
}

it('marks the order paid with the totals and address stripe collected', function (): void {
    $order = pendingOrder();
    stripe()->respond('GET', '/v1/checkout/sessions/cs_test_1', stripeSession('cs_test_1'));

    resolve(FulfillCheckoutAction::class)->handle('cs_test_1');

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::PAID)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->only(['email', 'name', 'subtotal_amount', 'shipping_amount', 'tax_amount', 'total_amount', 'shipping_method', 'stripe_payment_intent']))
        ->toBe(['email' => 'buyer@example.com', 'name' => 'Sam Buyer', 'subtotal_amount' => 2000, 'shipping_amount' => 500, 'tax_amount' => 150, 'total_amount' => 2650, 'shipping_method' => 'Standard delivery', 'stripe_payment_intent' => 'pi_123'])
        ->and($order->shipping_address['address']['city'])->toBe('Toronto');

    stripe()->assertSent('GET', '/v1/checkout/sessions/cs_test_1', fn (array $params): bool => $params['expand'] === ['shipping_cost.shipping_rate']);
});

it('fulfils an order only once', function (): void {
    $order = pendingOrder();
    stripe()->respond('GET', '/v1/checkout/sessions/cs_test_1', stripeSession('cs_test_1'));

    resolve(FulfillCheckoutAction::class)->handle('cs_test_1');
    $paidAt = $order->refresh()->paid_at;

    $this->travel(1)->hour();
    resolve(FulfillCheckoutAction::class)->handle('cs_test_1');

    expect($order->refresh()->paid_at?->equalTo($paidAt))->toBeTrue();
});

it('waits for a delayed payment method to clear', function (): void {
    $order = pendingOrder();
    stripe()->respond('GET', '/v1/checkout/sessions/cs_test_1', stripeSession('cs_test_1', ['payment_status' => 'unpaid', 'payment_intent' => ['id' => 'pi_obj', 'object' => 'payment_intent']]));

    resolve(FulfillCheckoutAction::class)->handle('cs_test_1');

    expect($order->refresh()->status)->toBe(OrderStatus::PROCESSING)
        ->and($order->stripe_payment_intent)->toBe('pi_obj');
});

it('leaves the order alone while the checkout is still open', function (): void {
    $order = pendingOrder();
    stripe()->respond('GET', '/v1/checkout/sessions/cs_test_1', stripeSession('cs_test_1', ['status' => 'open']));

    resolve(FulfillCheckoutAction::class)->handle('cs_test_1');

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
});

it('ignores a checkout that belongs to no order', function (): void {
    expect(resolve(FulfillCheckoutAction::class)->handle('cs_test_unknown'))->toBeNull();

    stripe()->assertNothingSent();
});

it('keeps a payment that arrives after the order was cancelled and takes the stock again', function (): void {
    $record = sellableRecord(attributes: ['stock' => 5]);
    $order = pendingOrder($record, quantity: 2, attributes: ['status' => OrderStatus::CANCELLED, 'cancelled_at' => now()]);
    stripe()->respond('GET', '/v1/checkout/sessions/cs_test_1', stripeSession('cs_test_1'));

    resolve(FulfillCheckoutAction::class)->handle('cs_test_1');

    expect($order->refresh()->status)->toBe(OrderStatus::PAID)
        ->and($order->oversold)->toBeFalse()
        ->and($order->cancelled_at)->toBeNull()
        ->and($record->refresh()->stock)->toBe(3);
});

it('flags a late payment as oversold when the stock is gone', function (): void {
    $record = sellableRecord(attributes: ['stock' => 1]);
    $untracked = sellableRecord();
    $order = pendingOrder($record, quantity: 2, attributes: ['status' => OrderStatus::CANCELLED]);
    OrderItem::factory()->for($order)->create(['record_id' => $untracked->id]);
    OrderItem::factory()->for($order)->create(['record_id' => 999_999]);
    stripe()->respond('GET', '/v1/checkout/sessions/cs_test_1', stripeSession('cs_test_1'));

    resolve(FulfillCheckoutAction::class)->handle('cs_test_1');

    expect($order->refresh()->oversold)->toBeTrue()
        ->and($record->refresh()->stock)->toBe(0)
        ->and($untracked->refresh()->stock)->toBeNull();
});

it('releases the reserved stock and cancels the order', function (): void {
    $record = sellableRecord(attributes: ['stock' => 1]);
    $order = pendingOrder($record, quantity: 2, reserved: 2);

    resolve(ReleaseOrderAction::class)->handle($order);
    resolve(ReleaseOrderAction::class)->handle($order);

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($order->items->sole()->reserved_stock)->toBe(0)
        ->and($record->refresh()->stock)->toBe(3);
});

it('never releases a paid order', function (): void {
    $record = sellableRecord(attributes: ['stock' => 1]);
    $order = pendingOrder($record, reserved: 2, attributes: ['status' => OrderStatus::PAID]);

    resolve(ReleaseOrderAction::class)->handle($order);

    expect($order->refresh()->status)->toBe(OrderStatus::PAID)
        ->and($record->refresh()->stock)->toBe(1);
});

it('records full and partial refunds', function (): void {
    $partial = Order::factory()->paid()->create(['stripe_payment_intent' => 'pi_partial']);
    $full = Order::factory()->paid()->create(['stripe_payment_intent' => 'pi_full']);
    $cancelled = Order::factory()->create(['status' => OrderStatus::CANCELLED, 'stripe_payment_intent' => 'pi_cancelled']);

    resolve(RefundOrderAction::class)->handle('pi_partial', 500, false);
    resolve(RefundOrderAction::class)->handle('pi_full', 2000, true);
    resolve(RefundOrderAction::class)->handle('pi_cancelled', 2000, true);

    expect($partial->refresh()->only(['status', 'refunded_amount']))->toBe(['status' => OrderStatus::PAID, 'refunded_amount' => 500])
        ->and($full->refresh()->only(['status', 'refunded_amount']))->toBe(['status' => OrderStatus::REFUNDED, 'refunded_amount' => 2000])
        ->and($cancelled->refresh()->refunded_amount)->toBe(0)
        ->and(resolve(RefundOrderAction::class)->handle('pi_unknown', 100, true))->toBeNull();
});
