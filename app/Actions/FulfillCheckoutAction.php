<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Record;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;

final readonly class FulfillCheckoutAction
{
    public function __construct(private StripeService $stripe) {}

    /**
     * @throws ApiErrorException
     */
    public function handle(string $sessionId): ?Order
    {
        $order = Order::query()->where('stripe_session_id', $sessionId)->first();

        if (! $order instanceof Order) {
            return null;
        }

        $session = $this->stripe->retrieveCheckoutSession($sessionId);

        if (data_get($session, 'status') !== 'complete') {
            return $order;
        }

        $paid = in_array(data_get($session, 'payment_status'), ['paid', 'no_payment_required'], true);

        return DB::transaction(function () use ($order, $session, $paid): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($paid && $locked->status->canBecomePaid()) {
                $oversold = $locked->status === OrderStatus::CANCELLED && $this->reserveAgain($locked);

                $locked->update([
                    ...$this->details($session),
                    'status' => OrderStatus::PAID,
                    'paid_at' => now(),
                    'cancelled_at' => null,
                    'oversold' => $oversold,
                ]);
            } elseif (! $paid && $locked->status === OrderStatus::PENDING) {
                $locked->update([...$this->details($session), 'status' => OrderStatus::PROCESSING]);
            }

            return $locked;
        });
    }

    private function reserveAgain(Order $order): bool
    {
        $oversold = false;

        foreach ($order->items()->whereNotNull('record_id')->get() as $item) {
            $record = Record::query()->find($item->record_id);

            if (! $record instanceof Record || $record->stock === null) {
                continue;
            }

            $taken = Record::query()->whereKey($record->id)->where('stock', '>=', $item->quantity)->decrement('stock', $item->quantity);

            if ($taken === 0) {
                Record::query()->whereKey($record->id)->update(['stock' => 0]);
                $oversold = true;
            }
        }

        return $oversold;
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function details(array $session): array
    {
        $shipping = data_get($session, 'collected_information.shipping_details');
        $paymentIntent = data_get($session, 'payment_intent');
        $shippingMethod = data_get($session, 'shipping_cost.shipping_rate.display_name');

        return [
            'email' => data_get($session, 'customer_details.email'),
            'name' => data_get($session, 'customer_details.name'),
            'subtotal_amount' => (int) data_get($session, 'amount_subtotal', 0),
            'shipping_amount' => (int) data_get($session, 'total_details.amount_shipping', 0),
            'tax_amount' => (int) data_get($session, 'total_details.amount_tax', 0),
            'discount_amount' => (int) data_get($session, 'total_details.amount_discount', 0),
            'total_amount' => (int) data_get($session, 'amount_total', 0),
            'shipping_address' => is_array($shipping) ? $shipping : null,
            'shipping_method' => is_string($shippingMethod) ? $shippingMethod : null,
            'stripe_payment_intent' => is_string($paymentIntent) ? $paymentIntent : data_get($session, 'payment_intent.id'),
        ];
    }
}
