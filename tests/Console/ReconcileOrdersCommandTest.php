<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;

function staleOrder(array $attributes = []): Order
{
    return Order::factory()->create(['expires_at' => now()->subMinutes(10), ...$attributes]);
}

it('does nothing while stripe is not connected', function (): void {
    $order = staleOrder();

    $this->artisan('wireup:reconcile-orders')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
});

it('settles every checkout that ended without reaching the site', function (): void {
    connectStripe();
    $completed = staleOrder(['stripe_session_id' => 'cs_complete']);
    $open = staleOrder(['stripe_session_id' => 'cs_open']);
    $expired = staleOrder(['stripe_session_id' => 'cs_expired']);
    $orphan = staleOrder(['stripe_session_id' => null]);
    $recent = Order::factory()->create(['stripe_session_id' => 'cs_recent']);
    stripe()
        ->respond('GET', '/v1/checkout/sessions/cs_complete', stripeSession('cs_complete'))
        ->respond('GET', '/v1/checkout/sessions/cs_open', stripeSession('cs_open', ['status' => 'open']))
        ->respond('GET', '/v1/checkout/sessions/cs_expired', stripeSession('cs_expired', ['status' => 'expired']))
        ->respond('POST', '/v1/checkout/sessions/cs_open/expire', stripeSession('cs_open', ['status' => 'expired']));

    $this->artisan('wireup:reconcile-orders')
        ->expectsOutputToContain('Checked 4 unfinished orders.')
        ->assertSuccessful();

    expect($completed->refresh()->status)->toBe(OrderStatus::PAID)
        ->and($open->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($expired->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($orphan->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($recent->refresh()->status)->toBe(OrderStatus::PENDING);

    stripe()->assertSent('POST', '/v1/checkout/sessions/cs_open/expire');
});

it('keeps an order pending when stripe cannot be reached', function (): void {
    connectStripe();
    $order = staleOrder(['stripe_session_id' => 'cs_unreachable', 'reference' => 'ABCD1234']);
    stripe()->fail('GET', '/v1/checkout/sessions/cs_unreachable', 'Stripe is down', 500);

    $this->artisan('wireup:reconcile-orders')
        ->expectsOutputToContain('Could not check order ABCD1234 with Stripe.')
        ->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
});
