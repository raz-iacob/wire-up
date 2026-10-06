<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\FulfillCheckoutAction;
use App\Actions\ReleaseOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\StripeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Stripe\Exception\ApiErrorException;

#[Description('Settle checkouts whose Stripe session ended without reaching this site')]
#[Signature('wireup:reconcile-orders')]
final class ReconcileOrdersCommand extends Command
{
    public function handle(StripeService $stripe, FulfillCheckoutAction $fulfil, ReleaseOrderAction $release): int
    {
        if (! $stripe->configured()) {
            return self::SUCCESS;
        }

        $orders = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->where('expires_at', '<', now()->subMinutes(5))
            ->get();

        foreach ($orders as $order) {
            if ($order->stripe_session_id === null) {
                $release->handle($order);

                continue;
            }

            try {
                match (data_get($stripe->retrieveCheckoutSession($order->stripe_session_id), 'status')) {
                    'complete' => $fulfil->handle($order->stripe_session_id),
                    'open' => $this->expireThenRelease($stripe, $release, $order, $order->stripe_session_id),
                    default => $release->handle($order),
                };
            } catch (ApiErrorException $exception) {
                report($exception);

                $this->components->warn("Could not check order {$order->reference} with Stripe.");
            }
        }

        $this->components->info("Checked {$orders->count()} unfinished orders.");

        return self::SUCCESS;
    }

    /**
     * @throws ApiErrorException
     */
    private function expireThenRelease(StripeService $stripe, ReleaseOrderAction $release, Order $order, string $sessionId): void
    {
        $stripe->expireCheckoutSession($sessionId);

        $release->handle($order);
    }
}
