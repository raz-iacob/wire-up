<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Record;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;

final readonly class IssueRefundAction
{
    public function __construct(private StripeService $stripe) {}

    /**
     * @throws ApiErrorException
     * @throws ValidationException
     */
    public function handle(Order $order, int $amount, bool $restock): void
    {
        $refundable = $order->total_amount - $order->refunded_amount;

        throw_if(
            ! $order->status->canBeRefunded() || $order->stripe_payment_intent === null,
            ValidationException::withMessages(['refundAmount' => __('This order cannot be refunded.')]),
        );

        throw_if(
            $amount < 1 || $amount > $refundable,
            ValidationException::withMessages(['refundAmount' => __('Enter an amount up to what is left to refund.')]),
        );

        $this->stripe->refund($order->stripe_payment_intent, $amount);

        DB::transaction(function () use ($order, $amount, $refundable, $restock): void {
            if ($restock && $order->restocked_at === null) {
                foreach ($order->items()->whereNotNull('record_id')->get() as $item) {
                    Record::query()->whereKey($item->record_id)->whereNotNull('stock')->increment('stock', $item->quantity);
                }
            }

            $order->update([
                'refunded_amount' => $order->refunded_amount + $amount,
                'status' => $amount === $refundable ? OrderStatus::REFUNDED : $order->status,
                'restocked_at' => $restock && $order->restocked_at === null ? now() : $order->restocked_at,
            ]);
        });
    }
}
