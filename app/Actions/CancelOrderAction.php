<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\StripeService;
use Stripe\Exception\ApiErrorException;

final readonly class CancelOrderAction
{
    public function __construct(
        private StripeService $stripe,
        private ReleaseOrderAction $release,
        private FulfillCheckoutAction $fulfil,
    ) {}

    /**
     * @throws ApiErrorException
     */
    public function handle(Order $order): bool
    {
        if ($order->status !== OrderStatus::PENDING) {
            return false;
        }

        if ($order->stripe_session_id !== null) {
            try {
                $this->stripe->expireCheckoutSession($order->stripe_session_id);
            } catch (ApiErrorException) {
                $this->fulfil->handle($order->stripe_session_id);

                return $order->refresh()->status === OrderStatus::CANCELLED;
            }
        }

        $this->release->handle($order);

        return true;
    }
}
