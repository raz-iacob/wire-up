<?php

declare(strict_types=1);

use App\Actions\CreateCheckoutAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\CartService;

beforeEach(function (): void {
    connectStripe();
});

function successUrl(string $sessionId): string
{
    return route('checkout.success').'?session_id='.$sessionId;
}

it('confirms the order to the shopper who placed it and empties their cart', function (): void {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_done', 'reference' => 'WXYZ9876']);
    OrderItem::factory()->for($order)->create(['name' => 'Walnut lamp']);
    stripe()->respond('GET', '/v1/checkout/sessions/cs_done', stripeSession('cs_done'));

    $this->withSession([CreateCheckoutAction::LAST_ORDER_KEY => $order->id, CartService::SESSION_KEY => [5 => 1]])
        ->get(successUrl('cs_done'))
        ->assertOk()
        ->assertSee(__('Your order :reference is confirmed.', ['reference' => 'WXYZ9876']))
        ->assertSee('Walnut lamp')
        ->assertSessionMissing(CartService::SESSION_KEY);

    expect($order->refresh()->status)->toBe(OrderStatus::PAID);
});

it('shows a signed-in customer their own order', function (): void {
    $user = User::factory()->member()->create();
    $order = Order::factory()->create(['stripe_session_id' => 'cs_member', 'user_id' => $user->id]);
    stripe()->respond('GET', '/v1/checkout/sessions/cs_member', stripeSession('cs_member', ['payment_status' => 'unpaid']));

    $this->actingAs($user)
        ->get(successUrl('cs_member'))
        ->assertSee(__('Your payment is being processed. We will email you once it clears.'));
});

it('hides the order from anyone else holding the link', function (): void {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_private', 'reference' => 'SECRET01']);
    stripe()->respond('GET', '/v1/checkout/sessions/cs_private', stripeSession('cs_private'));

    $this->withSession([CartService::SESSION_KEY => [5 => 1]])
        ->get(successUrl('cs_private'))
        ->assertOk()
        ->assertSee(__('Your order has been received.'))
        ->assertDontSee('SECRET01')
        ->assertSessionHas(CartService::SESSION_KEY);
});

it('still thanks the shopper when stripe cannot be reached', function (): void {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_offline']);
    stripe()->fail('GET', '/v1/checkout/sessions/cs_offline', 'Stripe is down', 500);

    $this->withSession([CreateCheckoutAction::LAST_ORDER_KEY => $order->id])
        ->get(successUrl('cs_offline'))
        ->assertOk()
        ->assertSee(__('We are confirming your payment with the bank. This page will show your order once it does.'));
});

it('thanks a visitor who arrives without a checkout', function (): void {
    $this->get(route('checkout.success'))
        ->assertOk()
        ->assertSee(__('Your order has been received.'));

    stripe()->assertNothingSent();
});
