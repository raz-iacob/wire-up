<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\ActivityLog;

final readonly class RefundOrderAction
{
    public function handle(string $paymentIntent, int $amountRefunded, bool $fullyRefunded): ?Order
    {
        $order = Order::query()->where('stripe_payment_intent', $paymentIntent)->first();

        if (! $order instanceof Order || ! $order->status->canBeRefunded()) {
            return $order;
        }

        $previouslyRefunded = $order->refunded_amount;

        $order->update([
            'refunded_amount' => $amountRefunded,
            'status' => $fullyRefunded ? OrderStatus::REFUNDED : $order->status,
        ]);

        if ($amountRefunded > $previouslyRefunded) {
            ActivityLog::record(ActivityEvent::ORDER_REFUNDED, $order, ['amount' => $amountRefunded - $previouslyRefunded, 'currency' => $order->currency]);
        }

        return $order;
    }
}
