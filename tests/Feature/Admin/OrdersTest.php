<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\User;
use App\Services\ShopService;
use Livewire\Livewire;

function orderStaff(array $abilities): User
{
    $role = Role::factory()->create(['abilities' => ['pages.view', ...$abilities]]);

    return User::factory()->role($role)->create(['active' => true]);
}

it('lists orders for staff who may view them', function (): void {
    $order = Order::factory()->paid()->create(['reference' => 'LIST0001', 'name' => 'Ada Shopper']);

    $this->actingAsAdmin()
        ->get(route('admin.orders-index'))
        ->assertOk()
        ->assertSee('LIST0001')
        ->assertSee('Ada Shopper')
        ->assertSee(OrderStatus::PAID->label());
});

it('keeps orders away from staff without the orders ability and from members', function (): void {
    $order = Order::factory()->create();

    $this->actingAs(orderStaff([]))
        ->get(route('admin.orders-index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->member()->create(['active' => true]))
        ->fromRoute('home')
        ->get(route('admin.orders-show', $order))
        ->assertRedirectToRoute('home');
});

it('filters, searches and sorts the orders', function (): void {
    Order::factory()->paid()->create(['reference' => 'PAID0001', 'total_amount' => 900]);
    Order::factory()->paid()->create(['reference' => 'PAID0002', 'total_amount' => 5000, 'email' => 'big@example.com']);
    Order::factory()->create(['reference' => 'WAIT0001', 'status' => OrderStatus::PENDING]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.orders-index')
        ->set('status', 'paid')
        ->assertSee('PAID0001')
        ->assertDontSee('WAIT0001')
        ->set('search', 'big@')
        ->assertSee('PAID0002')
        ->assertDontSee('PAID0001')
        ->set('search', '')
        ->call('sort', 'total_amount')
        ->assertSeeInOrder(['PAID0002', 'PAID0001'])
        ->call('sort', 'total_amount')
        ->assertSeeInOrder(['PAID0001', 'PAID0002'])
        ->call('sort', 'email')
        ->assertSet('sortBy', 'total_amount');
});

it('shows an empty list', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.orders-index')->assertSee(__('No orders yet.'));
});

it('shows an order with its items, totals, address and stripe link', function (): void {
    connectStripe();
    $order = Order::factory()->paid()->create([
        'reference' => 'SHOW0001',
        'shipping_amount' => 500,
        'tax_amount' => 150,
        'discount_amount' => 100,
        'refunded_amount' => 200,
        'shipping_method' => 'Standard delivery',
        'shipping_address' => ['name' => 'Ada Shopper', 'address' => ['line1' => '1 Main St', 'city' => 'Toronto']],
        'oversold' => true,
        'stripe_payment_intent' => 'pi_show',
        'user_id' => User::factory()->member()->create()->id,
    ]);
    OrderItem::factory()->for($order)->create(['name' => 'Walnut lamp', 'sku' => 'LAMP-1']);

    $this->actingAsAdmin()
        ->get(route('admin.orders-show', $order))
        ->assertOk()
        ->assertSee('SHOW0001')
        ->assertSee('Walnut lamp')
        ->assertSee('LAMP-1')
        ->assertSee('1 Main St')
        ->assertSee('Standard delivery')
        ->assertSee(__('Oversold'))
        ->assertSee(__('Signed in as a member'))
        ->assertSee('https://dashboard.stripe.com/test/payments/pi_show');
});

it('marks a paid order as fulfilled', function (): void {
    $order = Order::factory()->paid()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.orders-show', ['order' => $order])
        ->call('markFulfilled')
        ->assertSee(__('Fulfilled :date', ['date' => now()->format('M d, Y H:i')]));

    expect($order->refresh()->status)->toBe(OrderStatus::FULFILLED);
});

it('leaves an unpaid order as it is', function (): void {
    $order = Order::factory()->create(['status' => OrderStatus::PENDING, 'stripe_payment_intent' => null]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.orders-show', ['order' => $order])
        ->assertDontSee(__('Mark as fulfilled'))
        ->call('markFulfilled');

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
});

it('needs the orders edit ability to fulfil an order', function (): void {
    $order = Order::factory()->paid()->create();

    $this->actingAs(orderStaff(['orders.view']));

    Livewire::test('pages::admin.orders-show', ['order' => $order])
        ->assertDontSee(__('Mark as fulfilled'))
        ->call('markFulfilled')
        ->assertForbidden();
});

it('links live payments to the live stripe dashboard', function (): void {
    config(['cashier.secret' => 'sk_live_1']);

    expect(Order::factory()->paid()->make(['stripe_payment_intent' => 'pi_live'])->stripeDashboardUrl())
        ->toBe('https://dashboard.stripe.com/payments/pi_live')
        ->and(Order::factory()->make()->stripeDashboardUrl())->toBeNull();
});

it('counts paid orders waiting to be fulfilled in the sidebar', function (): void {
    Order::factory()->paid()->count(2)->create();
    Order::factory()->create(['status' => OrderStatus::FULFILLED]);

    $this->actingAsAdmin();

    $this->blade('<x-admin.sidebar />')->assertSee(route('admin.orders-index'));

    expect(Order::awaitingFulfilmentCount())->toBe(2);
});

it('hides orders from the sidebar and dashboard until the shop is in use', function (): void {
    $this->actingAsAdmin();

    $this->blade('<x-admin.sidebar />')->assertDontSee(route('admin.orders-index'));

    $this->get(route('admin.dashboard'))->assertDontSee(__('Latest orders'));
});

it('still shows an order after stripe is disconnected', function (): void {
    $order = Order::factory()->paid()->create(['reference' => 'GONE0001']);

    $this->actingAsAdmin()
        ->get(route('admin.orders-show', $order))
        ->assertOk()
        ->assertSee('https://dashboard.stripe.com/payments/');
});

it('shows the latest orders and this month’s takings on the dashboard', function (): void {
    connectStripe();
    Order::factory()->paid()->create(['name' => 'Ada Shopper', 'total_amount' => 3000, 'refunded_amount' => 500]);
    Order::factory()->paid()->create(['total_amount' => 9900, 'paid_at' => now()->subMonths(2)]);

    $this->actingAsAdmin()
        ->get(route('admin.dashboard'))
        ->assertSee(__('Latest orders'))
        ->assertSee('Ada Shopper')
        ->assertSee(__(':amount this month', ['amount' => resolve(ShopService::class)->formatMinor(2500)]));
});

it('labels and colours every order status', function (OrderStatus $status): void {
    expect($status->label())->not->toBe('')
        ->and($status->color())->not->toBe('');
})->with(OrderStatus::cases());
