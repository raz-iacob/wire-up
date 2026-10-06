<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\FulfillCheckoutAction;
use App\Actions\RefundOrderAction;
use App\Actions\ReleaseOrderAction;
use App\Models\Order;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;

final class StripeWebhookController extends WebhookController
{
    public function __invoke(Request $request): Response
    {
        abort_if(blank(config('cashier.webhook.secret')), Response::HTTP_FORBIDDEN);

        return $this->handleWebhook($request);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        resolve(FulfillCheckoutAction::class)->handle((string) data_get($payload, 'data.object.id'));

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutSessionAsyncPaymentSucceeded(array $payload): Response
    {
        return $this->handleCheckoutSessionCompleted($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutSessionAsyncPaymentFailed(array $payload): Response
    {
        $order = Order::query()->where('stripe_session_id', (string) data_get($payload, 'data.object.id'))->first();

        if ($order instanceof Order) {
            resolve(ReleaseOrderAction::class)->handle($order);
        }

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutSessionExpired(array $payload): Response
    {
        return $this->handleCheckoutSessionAsyncPaymentFailed($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeRefunded(array $payload): Response
    {
        $paymentIntent = data_get($payload, 'data.object.payment_intent');

        if (is_string($paymentIntent)) {
            resolve(RefundOrderAction::class)->handle(
                $paymentIntent,
                (int) data_get($payload, 'data.object.amount_refunded', 0),
                (bool) data_get($payload, 'data.object.refunded', false),
            );
        }

        return $this->successMethod();
    }
}
