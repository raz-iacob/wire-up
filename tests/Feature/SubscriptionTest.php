<?php

declare(strict_types=1);

use App\Actions\StartSubscriptionCheckoutAction;
use App\Models\Record;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

beforeEach(function (): void {
    connectStripe();
});

function subscriptionRecord(string $billing = 'Monthly'): Record
{
    return sellableRecord(['current_price' => '9.00', 'billing' => $billing, 'heading' => ['en' => 'Studio membership']]);
}

function fakeSubscriptionCheckout(): void
{
    stripe()
        ->respond('POST', '/v1/customers', ['id' => 'cus_new', 'object' => 'customer'])
        ->respond('POST', '/v1/checkout/sessions', ['id' => 'cs_sub', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_sub']);
}

/**
 * @return array<string, mixed>
 */
function stripeSubscription(string $customer, Record $record): array
{
    return [
        'id' => 'sub_123',
        'object' => 'subscription',
        'customer' => $customer,
        'status' => 'active',
        'metadata' => ['type' => StartSubscriptionCheckoutAction::typeFor($record), 'name' => StartSubscriptionCheckoutAction::typeFor($record)],
        'items' => ['object' => 'list', 'data' => [['id' => 'si_1', 'object' => 'subscription_item', 'quantity' => 1, 'price' => ['id' => 'price_1', 'object' => 'price', 'product' => 'prod_1']]]],
    ];
}

it('shows a subscribe button with the billing interval', function (string $billing, string $note): void {
    $record = subscriptionRecord($billing);

    $this->get($record->getUrl())
        ->assertOk()
        ->assertSee(__('Subscribe'))
        ->assertSee(__($note))
        ->assertDontSee(__('Add to cart'));
})->with([
    'monthly' => ['Monthly', 'Billed every month until you cancel.'],
    'yearly' => ['Yearly', 'Billed every year until you cancel.'],
]);

it('sends a guest to sign in and back to the product', function (): void {
    $record = subscriptionRecord();

    Livewire::test('site.buy-box', ['record' => $record])
        ->call('subscribe')
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe($record->getUrl());
    stripe()->assertNothingSent();
});

it('opens stripe checkout for a recurring price at the record price', function (): void {
    fakeSubscriptionCheckout();
    $record = subscriptionRecord();
    $user = User::factory()->member()->create();

    $this->actingAs($user);

    Livewire::test('site.buy-box', ['record' => $record])
        ->call('subscribe')
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_sub');

    expect($user->refresh()->stripe_id)->toBe('cus_new');

    stripe()->assertSent('POST', '/v1/checkout/sessions', fn (array $params): bool => $params['mode'] === 'subscription'
        && $params['customer'] === 'cus_new'
        && $params['line_items'][0]['price_data']['unit_amount'] === 900
        && $params['line_items'][0]['price_data']['recurring']['interval'] === 'month'
        && $params['subscription_data']['metadata']['type'] === 'record-'.$record->id
        && str_contains($params['success_url'], 'subscription_session={CHECKOUT_SESSION_ID}')
        && ! isset($params['automatic_tax']));
});

it('bills yearly with stripe tax when the shop calculates tax', function (): void {
    fakeSubscriptionCheckout();
    Settings::set(['shop_automatic_tax' => true]);
    $user = User::factory()->member()->create();

    resolve(StartSubscriptionCheckoutAction::class)->handle($user, subscriptionRecord('Yearly'));

    stripe()->assertSent('POST', '/v1/checkout/sessions', fn (array $params): bool => $params['line_items'][0]['price_data']['recurring']['interval'] === 'year'
        && $params['line_items'][0]['price_data']['tax_behavior'] === 'exclusive'
        && $params['automatic_tax'] === ['enabled' => 'true']
        && $params['customer_update'] === ['address' => 'auto']);
});

it('refuses to start a subscription that cannot be bought', function (Closure $setup, string $message): void {
    [$user, $record] = $setup();

    $this->actingAs($user);

    Livewire::test('site.buy-box', ['record' => $record])
        ->call('subscribe')
        ->assertHasErrors('subscribe')
        ->assertSee(__($message));

    stripe()->assertNothingSent();
})->with([
    'unverified email' => [fn (): array => [User::factory()->member()->unverified()->create(), subscriptionRecord()], 'Confirm your email address from your account page before subscribing.'],
    'already subscribed' => [function (): array {
        $user = User::factory()->member()->stripeCustomer()->create();
        $record = subscriptionRecord();
        Subscription::query()->create(['user_id' => $user->id, 'type' => 'record-'.$record->id, 'stripe_id' => 'sub_existing', 'stripe_status' => 'active']);

        return [$user, $record];
    }, 'You already subscribe to this.'],
]);

it('refuses to subscribe to a one-off product', function (): void {
    expect(fn (): string => resolve(StartSubscriptionCheckoutAction::class)->handle(User::factory()->member()->create(), sellableRecord()))
        ->toThrow(ValidationException::class, __('This subscription is no longer available.'));

    stripe()->assertNothingSent();
});

it('lets an unverified member subscribe while email is not set up', function (): void {
    fakeSubscriptionCheckout();
    config(['mail.default' => 'log']);

    expect(resolve(StartSubscriptionCheckoutAction::class)->handle(User::factory()->member()->unverified()->create(), subscriptionRecord()))
        ->toBe('https://checkout.stripe.com/c/pay/cs_sub');
});

it('apologises when stripe cannot start the subscription', function (): void {
    stripe()->fail('POST', '/v1/customers', 'Stripe is down', 500);

    $this->actingAs(User::factory()->member()->create());

    Livewire::test('site.buy-box', ['record' => subscriptionRecord()])
        ->call('subscribe')
        ->assertHasErrors(['subscribe' => __('Subscribing is not available right now. Please try again later.')]);
});

it('records the subscription when the subscriber comes back from stripe', function (): void {
    $user = User::factory()->member()->stripeCustomer()->create();
    $record = subscriptionRecord();
    stripe()->respond('GET', '/v1/checkout/sessions/cs_sub', ['id' => 'cs_sub', 'object' => 'checkout.session', 'customer' => $user->stripe_id, 'subscription' => stripeSubscription((string) $user->stripe_id, $record)]);

    $this->actingAs($user)
        ->get(route('account').'?subscription_session=cs_sub')
        ->assertOk()
        ->assertSee('Studio membership')
        ->assertSee(__('Renews automatically'));

    $this->get(route('account').'?subscription_session=cs_sub')->assertOk();

    expect(Subscription::query()->where('user_id', $user->id)->sole()->type)->toBe('record-'.$record->id);
    stripe()->assertSent('GET', '/v1/checkout/sessions/cs_sub', fn (array $params): bool => $params['expand'] === ['subscription']);
});

it('ignores a returning checkout that belongs to someone else', function (): void {
    $user = User::factory()->member()->stripeCustomer()->create();
    stripe()->respond('GET', '/v1/checkout/sessions/cs_other', ['id' => 'cs_other', 'object' => 'checkout.session', 'customer' => 'cus_someone_else', 'subscription' => stripeSubscription('cus_someone_else', subscriptionRecord())]);

    $this->actingAs($user)
        ->get(route('account').'?subscription_session=cs_other')
        ->assertSee(__('Thank you for subscribing. Your subscription will appear here in a moment.'));

    expect(Subscription::query()->count())->toBe(0);
});

it('still thanks the subscriber when stripe cannot be reached', function (): void {
    stripe()->fail('GET', '/v1/checkout/sessions/cs_down', 'Stripe is down', 500);

    $this->actingAs(User::factory()->member()->stripeCustomer()->create())
        ->get(route('account').'?subscription_session=cs_down')
        ->assertOk()
        ->assertSee(__('Thank you for subscribing. Your subscription will appear here in a moment.'));
});

it('describes each subscription and opens the billing portal', function (): void {
    $user = User::factory()->member()->stripeCustomer()->create();
    $record = subscriptionRecord();
    $rows = [
        ['type' => 'record-'.$record->id, 'stripe_status' => 'active', 'ends_at' => null],
        ['type' => 'record-999999', 'stripe_status' => 'active', 'ends_at' => now()->addWeek()],
        ['type' => 'record-999998', 'stripe_status' => 'canceled', 'ends_at' => now()->subDay()],
        ['type' => 'record-999997', 'stripe_status' => 'past_due', 'ends_at' => null],
        ['type' => 'record-999996', 'stripe_status' => 'incomplete', 'ends_at' => null],
    ];

    foreach ($rows as $index => $row) {
        Subscription::query()->create(['user_id' => $user->id, 'stripe_id' => 'sub_'.$index, ...$row]);
    }

    stripe()->respond('POST', '/v1/billing_portal/sessions', ['id' => 'bps_1', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/1']);

    $this->actingAs($user);

    Livewire::test('pages::account')
        ->assertSee('Studio membership')
        ->assertSee(__('Ends :date', ['date' => now()->addWeek()->isoFormat('LL')]))
        ->assertSee(__('Ended'))
        ->assertSee(__('Payment failed. Update your card under Manage billing.'))
        ->assertSee(__('Waiting for payment'))
        ->call('manageBilling')
        ->assertRedirect('https://billing.stripe.com/p/session/1');
});

it('explains when the billing portal is unavailable', function (): void {
    $user = User::factory()->member()->stripeCustomer()->create();
    Subscription::query()->create(['user_id' => $user->id, 'type' => 'record-1', 'stripe_id' => 'sub_1', 'stripe_status' => 'active']);
    stripe()->fail('POST', '/v1/billing_portal/sessions', 'No configuration provided', 400);

    $this->actingAs($user);

    Livewire::test('pages::account')
        ->call('manageBilling')
        ->assertHasErrors(['billing' => __('Billing cannot be managed right now. Please try again later.')]);
});

it('hides the subscriptions section from members who never subscribed', function (): void {
    $this->actingAs(User::factory()->member()->create())
        ->get(route('account'))
        ->assertDontSee(__('Manage billing'));
});

it('cancels active subscriptions before deleting an account', function (): void {
    $user = User::factory()->member()->stripeCustomer()->create(['password' => 'secret-password']);
    Subscription::query()->create(['user_id' => $user->id, 'type' => 'record-1', 'stripe_id' => 'sub_live', 'stripe_status' => 'active']);
    Subscription::query()->create(['user_id' => $user->id, 'type' => 'record-2', 'stripe_id' => 'sub_ending', 'stripe_status' => 'active', 'ends_at' => now()->addDay()]);
    stripe()->respond('DELETE', '/v1/subscriptions/sub_live', ['id' => 'sub_live', 'object' => 'subscription', 'status' => 'canceled']);

    $this->actingAs($user);

    Livewire::test('pages::account')
        ->set('delete_password', 'secret-password')
        ->call('delete')
        ->assertRedirect(route('home'));

    stripe()->assertSent('DELETE', '/v1/subscriptions/sub_live');
    stripe()->assertNotSent('DELETE', '/v1/subscriptions/sub_ending');
    $this->assertModelMissing($user);
});

it('keeps the account when a subscription cannot be cancelled', function (): void {
    $user = User::factory()->member()->stripeCustomer()->create(['password' => 'secret-password']);
    Subscription::query()->create(['user_id' => $user->id, 'type' => 'record-1', 'stripe_id' => 'sub_live', 'stripe_status' => 'active']);
    stripe()->fail('DELETE', '/v1/subscriptions/sub_live', 'Stripe is down', 500);

    $this->actingAs($user);

    Livewire::test('pages::account')
        ->set('delete_password', 'secret-password')
        ->call('delete')
        ->assertHasErrors(['delete_password' => __('Your subscription could not be cancelled, so your account was kept. Please try again later.')]);

    $this->assertModelExists($user);
    $this->assertAuthenticatedAs($user);
});
