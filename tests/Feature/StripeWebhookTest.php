<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;

it('refuses every webhook while no signing secret is configured', function (): void {
    config(['cashier.webhook.secret' => null]);

    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1'], 'whsec_anything')
        ->assertForbidden();
});

it('rejects a webhook signed with the wrong secret', function (): void {
    config(['cashier.webhook.secret' => 'whsec_real']);

    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1'], 'whsec_forged')
        ->assertForbidden();
});

it('accepts a correctly signed webhook for an event it does not handle', function (): void {
    config(['cashier.webhook.secret' => 'whsec_real']);

    stripeWebhook('product.created', ['id' => 'prod_1'])
        ->assertOk();
});

it('fulfils the order when stripe reports a completed or cleared checkout', function (string $event): void {
    connectStripe();
    $order = Order::factory()->create(['stripe_session_id' => 'cs_test_hook']);
    stripe()->respond('GET', '/v1/checkout/sessions/cs_test_hook', stripeSession('cs_test_hook'));

    stripeWebhook($event, ['id' => 'cs_test_hook'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::PAID);
})->with(['checkout.session.completed', 'checkout.session.async_payment_succeeded']);

it('cancels the order when the checkout expires or the payment fails', function (string $event): void {
    config(['cashier.webhook.secret' => 'whsec_real']);
    $order = Order::factory()->create(['stripe_session_id' => 'cs_test_hook']);

    stripeWebhook($event, ['id' => 'cs_test_hook'])->assertOk();
    stripeWebhook($event, ['id' => 'cs_test_other'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
})->with(['checkout.session.expired', 'checkout.session.async_payment_failed']);

it('records a refund made in the stripe dashboard', function (): void {
    config(['cashier.webhook.secret' => 'whsec_real']);
    $order = Order::factory()->paid()->create(['stripe_payment_intent' => 'pi_hook']);

    stripeWebhook('charge.refunded', ['id' => 'ch_1', 'payment_intent' => 'pi_hook', 'amount_refunded' => 2000, 'refunded' => true])->assertOk();
    stripeWebhook('charge.refunded', ['id' => 'ch_2', 'payment_intent' => null])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED);
});
