<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\StripeService;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Exception\ApiErrorException;

final readonly class SyncSubscriptionFromCheckoutAction
{
    public function __construct(private StripeService $stripe) {}

    /**
     * @throws ApiErrorException
     */
    public function handle(User $user, string $sessionId): void
    {
        $session = $this->stripe->retrieveCheckoutSession($sessionId, ['subscription']);
        $subscription = data_get($session, 'subscription');

        if ($user->stripe_id === null || data_get($session, 'customer') !== $user->stripe_id || ! is_array($subscription)) {
            return;
        }

        if ($user->subscriptions()->where('stripe_id', data_get($subscription, 'id'))->exists()) {
            return;
        }

        new class extends WebhookController
        {
            /**
             * @param  array<string, mixed>  $subscription
             */
            public function sync(array $subscription): void
            {
                $this->handleCustomerSubscriptionCreated(['data' => ['object' => $subscription]]);
            }
        }->sync($subscription);
    }
}
