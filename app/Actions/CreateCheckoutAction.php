<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Record;
use App\Models\User;
use App\Services\CartService;
use App\Services\SettingsService;
use App\Services\ShopService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;

final readonly class CreateCheckoutAction
{
    public const string LAST_ORDER_KEY = 'last_order_id';

    public function __construct(
        private CartService $cart,
        private ShopService $shop,
        private StripeService $stripe,
        private ReleaseOrderAction $release,
    ) {}

    /**
     * @throws ApiErrorException
     * @throws ValidationException
     */
    public function handle(?User $user): string
    {
        $lines = $this->cart->lines();

        throw_if($lines === [], ValidationException::withMessages(['checkout' => __('Your cart is empty.')]));

        $needsShipping = array_any($lines, fn (array $line): bool => $this->shop->needsShipping($line['record']));

        throw_if(
            $needsShipping && $this->shop->shippingCountries() === [],
            ValidationException::withMessages(['checkout' => __('Checkout is not available yet. Please try again later.')]),
        );

        $order = DB::transaction(fn (): Order => $this->createOrder($lines, $user));

        try {
            $session = $this->stripe->createCheckoutSession($this->sessionParams($order, $lines, $needsShipping, $user));
        } catch (ApiErrorException $exception) {
            $this->release->handle($order);

            throw $exception;
        }

        $order->update(['stripe_session_id' => $session['id']]);

        session([self::LAST_ORDER_KEY => $order->id]);

        return $session['url'];
    }

    /**
     * @param  array<int, array{record: Record, quantity: int, unitAmount: int, lineAmount: int}>  $lines
     */
    private function createOrder(array $lines, ?User $user): Order
    {
        $subtotal = array_sum(array_column($lines, 'lineAmount'));

        $order = Order::query()->create([
            'reference' => $this->reference(),
            'status' => OrderStatus::PENDING,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'name' => $user?->name,
            'currency' => SettingsService::current()->currency(),
            'subtotal_amount' => $subtotal,
            'total_amount' => $subtotal,
            'locale' => app()->getLocale(),
            'expires_at' => now()->addMinutes(35),
        ]);

        foreach ($lines as $line) {
            $record = $line['record'];
            $reserved = $record->stock === null ? 0 : $line['quantity'];

            if ($reserved > 0) {
                $taken = Record::query()->whereKey($record->id)->whereNotNull('stock')->where('stock', '>=', $reserved)->decrement('stock', $reserved);

                throw_if($taken === 0, ValidationException::withMessages([
                    'checkout' => __(':item just sold out, so your cart has been updated.', ['item' => $record->displayHeading()]),
                ]));
            }

            $sku = $record->data['sku'] ?? null;

            $order->items()->create([
                'record_id' => $record->id,
                'name' => $record->displayHeading(),
                'sku' => is_string($sku) && $sku !== '' ? $sku : null,
                'unit_amount' => $line['unitAmount'],
                'quantity' => $line['quantity'],
                'line_amount' => $line['lineAmount'],
                'reserved_stock' => $reserved,
            ]);
        }

        return $order;
    }

    /**
     * @param  array<int, array{record: Record, quantity: int, unitAmount: int, lineAmount: int}>  $lines
     * @return array<string, mixed>
     */
    private function sessionParams(Order $order, array $lines, bool $needsShipping, ?User $user): array
    {
        $currency = Str::lower($order->currency);
        $taxBehavior = $this->shop->pricesIncludeTax() ? 'inclusive' : 'exclusive';
        $calculatesTax = $this->shop->calculatesTax();

        $params = [
            'mode' => 'payment',
            'line_items' => array_map(fn (array $line): array => [
                'quantity' => $line['quantity'],
                'price_data' => array_filter([
                    'currency' => $currency,
                    'unit_amount' => $line['unitAmount'],
                    'product_data' => ['name' => $line['record']->displayHeading(), 'metadata' => ['record_id' => (string) $line['record']->id]],
                    'tax_behavior' => $calculatesTax ? $taxBehavior : null,
                ]),
            ], $lines),
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('cart'),
            'client_reference_id' => (string) $order->id,
            'metadata' => ['order_id' => (string) $order->id, 'order_reference' => $order->reference],
            'expires_at' => $order->expires_at?->getTimestamp(),
            'locale' => 'auto',
        ];

        if ($user instanceof User) {
            $params['customer_email'] = $user->email;
        }

        if ($needsShipping) {
            $params['shipping_address_collection'] = ['allowed_countries' => $this->shop->shippingCountries()];
            $params['shipping_options'] = array_map(fn (array $rate): array => [
                'shipping_rate_data' => array_filter([
                    'type' => 'fixed_amount',
                    'display_name' => $rate['name'],
                    'fixed_amount' => ['amount' => $this->shop->toMinor($rate['amount']), 'currency' => $currency],
                    'tax_behavior' => $calculatesTax ? $taxBehavior : null,
                ]),
            ], $this->shop->shippingRates());
        }

        if ($calculatesTax) {
            $params['automatic_tax'] = ['enabled' => true];
        }

        return $params;
    }

    private function reference(): string
    {
        do {
            $reference = Str::upper(Str::random(8));
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
