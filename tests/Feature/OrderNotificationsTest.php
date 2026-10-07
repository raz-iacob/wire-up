<?php

declare(strict_types=1);

use App\Actions\FulfillCheckoutAction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Settings;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderReceived;
use App\Services\SlackWebhookChannel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    connectStripe();
});

function fulfilledOrder(array $session = []): Order
{
    $order = Order::factory()->create(['stripe_session_id' => 'cs_notify', 'locale' => 'en']);
    OrderItem::factory()->for($order)->create(['name' => 'Walnut lamp']);
    stripe()->respond('GET', '/v1/checkout/sessions/cs_notify', stripeSession('cs_notify', $session));

    resolve(FulfillCheckoutAction::class)->handle('cs_notify');

    return $order->refresh();
}

it('tells the owner and thanks the customer once an order is paid', function (): void {
    Notification::fake();
    Settings::set(['contact_email' => 'owner@example.com']);
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/T/B/x']);

    $order = fulfilledOrder();

    Notification::assertSentOnDemand(OrderReceived::class, fn (OrderReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes === ['mail' => 'owner@example.com'] && $notification->order->is($order));
    Notification::assertSentOnDemand(OrderReceived::class, fn (OrderReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => isset($notifiable->routes[SlackWebhookChannel::class]));
    Notification::assertSentOnDemand(OrderConfirmation::class, fn (OrderConfirmation $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes === ['mail' => 'buyer@example.com']);
});

it('sends the notifications only the first time an order is paid', function (): void {
    Notification::fake();
    Settings::set(['contact_email' => 'owner@example.com']);

    fulfilledOrder();
    resolve(FulfillCheckoutAction::class)->handle('cs_notify');

    Notification::assertSentOnDemandTimes(OrderReceived::class, 1);
    Notification::assertSentOnDemandTimes(OrderConfirmation::class, 1);
});

it('falls back to the sending address for the owner and skips the customer while email is not set up', function (): void {
    Notification::fake();
    config(['mail.default' => 'log', 'mail.from.address' => 'site@example.com']);

    fulfilledOrder();

    Notification::assertSentOnDemand(OrderReceived::class, fn (OrderReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes === ['mail' => 'site@example.com']);
    Notification::assertNotSentTo(new AnonymousNotifiable, OrderConfirmation::class);
    Notification::assertSentOnDemandTimes(OrderConfirmation::class, 0);
});

it('sends nothing to the owner when no address is known', function (): void {
    Notification::fake();
    config(['mail.from.address' => null]);

    fulfilledOrder(['customer_details' => ['email' => null, 'name' => null]]);

    Notification::assertNothingSent();
});

it('writes the owner email with the items, address and a link to the order', function (): void {
    $order = Order::factory()->paid()->create([
        'reference' => 'MAIL0001',
        'oversold' => true,
        'shipping_address' => ['name' => 'Ada Shopper', 'address' => ['line1' => '1 Main St', 'city' => 'Toronto']],
    ]);
    OrderItem::factory()->for($order)->create(['name' => 'Walnut lamp']);

    $mail = new OrderReceived($order)->toMail(new AnonymousNotifiable);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe(__('New order :reference', ['reference' => 'MAIL0001']))
        ->and($mail->replyTo)->toBe([['buyer@example.com', null]])
        ->and($html)->toContain('Walnut lamp', '1 Main St', route('admin.orders-show', $order))
        ->and(new OrderReceived($order)->via(new AnonymousNotifiable))->toBe([])
        ->and(new OrderReceived($order)->via($order))->toBe(['mail']);
});

it('posts the order to slack', function (): void {
    $order = Order::factory()->paid()->create(['reference' => 'SLCK0001', 'name' => 'Ada <Shopper>']);
    OrderItem::factory()->for($order)->create(['name' => 'Lamp & shade', 'quantity' => 2]);

    $payload = new OrderReceived($order)->toSlackWebhook();

    expect($payload['text'])->toBe(__('New order :reference', ['reference' => 'SLCK0001']))
        ->and($payload['blocks'][1]['text']['text'])->toContain('Ada &lt;Shopper&gt;', 'Lamp &amp; shade × 2')
        ->and($payload['blocks'][2]['elements'][0]['url'])->toBe(route('admin.orders-show', $order));
});

it('writes the customer receipt with the totals and replies to the site contact', function (): void {
    Settings::set(['contact_email' => 'hello@example.com']);
    $order = Order::factory()->paid()->create([
        'reference' => 'RCPT0001',
        'shipping_amount' => 500,
        'tax_amount' => 150,
        'shipping_address' => ['name' => 'Ada Shopper', 'address' => ['city' => 'Toronto']],
    ]);
    OrderItem::factory()->for($order)->create(['name' => 'Walnut lamp']);

    $notification = new OrderConfirmation($order);
    $mail = $notification->toMail(new AnonymousNotifiable);

    expect($notification->via(new AnonymousNotifiable))->toBe(['mail'])
        ->and($mail->subject)->toContain('RCPT0001')
        ->and($mail->replyTo)->toBe([['hello@example.com', null]])
        ->and((string) $mail->render())->toContain('Walnut lamp', 'Toronto');
});

it('writes a guest owner email without a reply-to address', function (): void {
    $order = Order::factory()->create(['email' => null]);

    expect(new OrderReceived($order)->toMail(new AnonymousNotifiable)->replyTo)->toBe([])
        ->and(new OrderConfirmation($order)->toMail(new AnonymousNotifiable)->replyTo)->toBe([]);
});
