<?php

declare(strict_types=1);

use App\Actions\CancelOrderAction;
use App\Actions\IssueRefundAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Record;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    connectStripe();
    stripe()->respond('POST', '/v1/refunds', ['id' => 're_1', 'object' => 'refund', 'status' => 'succeeded']);
});

function refundableOrder(?Record $record = null): Order
{
    $order = Order::factory()->paid()->create(['stripe_payment_intent' => 'pi_refund', 'total_amount' => 3000]);
    OrderItem::factory()->for($order)->create(['record_id' => $record?->id, 'quantity' => 2]);

    return $order;
}

it('refunds the whole order through stripe and can put the items back in stock', function (): void {
    $record = sellableRecord(attributes: ['stock' => 1]);
    $order = refundableOrder($record);

    resolve(IssueRefundAction::class)->handle($order, 3000, true);

    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED)
        ->and($order->refunded_amount)->toBe(3000)
        ->and($order->restocked_at)->not->toBeNull()
        ->and($record->refresh()->stock)->toBe(3);

    stripe()->assertSent('POST', '/v1/refunds', fn (array $params): bool => $params === ['payment_intent' => 'pi_refund', 'amount' => 3000]);
});

it('refunds part of an order and never restocks twice', function (): void {
    $record = sellableRecord(attributes: ['stock' => 1]);
    $order = refundableOrder($record);

    resolve(IssueRefundAction::class)->handle($order, 1000, true);
    resolve(IssueRefundAction::class)->handle($order->refresh(), 500, true);

    expect($order->refresh()->status)->toBe(OrderStatus::PAID)
        ->and($order->refunded_amount)->toBe(1500)
        ->and($record->refresh()->stock)->toBe(3);
});

it('refuses refunds that are too large or for orders that were never paid', function (Closure $order, int $amount, string $message): void {
    expect(fn () => resolve(IssueRefundAction::class)->handle($order(), $amount, false))
        ->toThrow(ValidationException::class, __($message));

    stripe()->assertNotSent('POST', '/v1/refunds');
})->with([
    'too much' => [fn (): Order => refundableOrder(), 3001, 'Enter an amount up to what is left to refund.'],
    'nothing' => [fn (): Order => refundableOrder(), 0, 'Enter an amount up to what is left to refund.'],
    'unpaid' => [fn (): Order => Order::factory()->create(), 100, 'This order cannot be refunded.'],
]);

it('refunds from the order page', function (): void {
    $order = refundableOrder(sellableRecord(attributes: ['stock' => 0]));

    $this->actingAsAdmin();

    Livewire::test('pages::admin.orders-show', ['order' => $order])
        ->assertSet('refundAmount', '30.00')
        ->assertSee(__('Put the items back in stock'))
        ->set('refundAmount', '12.50')
        ->call('refund')
        ->assertHasNoErrors()
        ->assertSet('showRefund', false)
        ->assertSet('refundAmount', '17.50');

    expect($order->refresh()->refunded_amount)->toBe(1250);
});

it('opens the refund form straight from the orders list', function (): void {
    $order = refundableOrder();

    $this->actingAsAdmin();

    Livewire::withQueryParams(['refund' => '1'])
        ->test('pages::admin.orders-show', ['order' => $order])
        ->assertSet('showRefund', true)
        ->assertDontSee(__('Put the items back in stock'));
});

it('shows what stripe said when a refund fails', function (): void {
    stripe()->fail('POST', '/v1/refunds', 'Charge has already been refunded', 400);
    $order = refundableOrder();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.orders-show', ['order' => $order])
        ->call('refund')
        ->assertHasErrors('refundAmount')
        ->assertSee('Charge has already been refunded');
});

it('needs the orders edit ability to refund', function (): void {
    $order = refundableOrder();
    $role = Role::factory()->create(['abilities' => ['orders.view']]);

    $this->actingAs(User::factory()->role($role)->create(['active' => true]));

    Livewire::test('pages::admin.orders-show', ['order' => $order])
        ->call('refund')
        ->assertForbidden();
});

it('cancels an unpaid order from the order page and from the list', function (string $page): void {
    $record = sellableRecord(attributes: ['stock' => 0]);
    $order = Order::factory()->create(['stripe_session_id' => 'cs_cancel']);
    OrderItem::factory()->for($order)->create(['record_id' => $record->id, 'quantity' => 2, 'reserved_stock' => 2]);
    stripe()->respond('POST', '/v1/checkout/sessions/cs_cancel/expire', stripeSession('cs_cancel', ['status' => 'expired']));

    $this->actingAsAdmin();

    $page === 'show'
        ? Livewire::test('pages::admin.orders-show', ['order' => $order])->assertSee(__('Cancel order'))->call('cancel')
        : Livewire::test('pages::admin.orders-index')->call('cancel', $order->id);

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($record->refresh()->stock)->toBe(2);
})->with(['show', 'index']);

it('keeps an order that was paid just before it was cancelled', function (): void {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_paid']);
    stripe()
        ->fail('POST', '/v1/checkout/sessions/cs_paid/expire', 'Session is already complete', 400)
        ->respond('GET', '/v1/checkout/sessions/cs_paid', stripeSession('cs_paid'));

    $this->actingAsAdmin();

    Livewire::test('pages::admin.orders-index')->call('cancel', $order->id);

    expect($order->refresh()->status)->toBe(OrderStatus::PAID);
});

it('tells staff when stripe cannot be reached to cancel', function (string $page): void {
    $order = Order::factory()->create(['stripe_session_id' => 'cs_down']);
    stripe()
        ->fail('POST', '/v1/checkout/sessions/cs_down/expire', 'Stripe is down', 500)
        ->fail('GET', '/v1/checkout/sessions/cs_down', 'Stripe is down', 500);

    $this->actingAsAdmin();

    $page === 'show'
        ? Livewire::test('pages::admin.orders-show', ['order' => $order])->call('cancel')
        : Livewire::test('pages::admin.orders-index')->call('cancel', $order->id);

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
})->with(['show', 'index']);

it('only cancels orders still waiting for payment', function (): void {
    expect(resolve(CancelOrderAction::class)->handle(Order::factory()->paid()->create()))->toBeFalse()
        ->and(resolve(CancelOrderAction::class)->handle(Order::factory()->create(['stripe_session_id' => null])))->toBeTrue();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.orders-index')->call('cancel', 999_999)->assertOk();
});

it('marks a paid order fulfilled from the list and shows each customer’s email', function (): void {
    $order = Order::factory()->paid()->create(['name' => 'Ada Shopper', 'email' => 'ada@example.com']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.orders-index')
        ->assertSee('ada@example.com')
        ->assertSee(route('admin.orders-show', ['order' => $order, 'refund' => 1]))
        ->call('markFulfilled', $order->id)
        ->call('markFulfilled', 999_999);

    expect($order->refresh()->status)->toBe(OrderStatus::FULFILLED);
});
