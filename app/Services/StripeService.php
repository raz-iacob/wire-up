<?php

declare(strict_types=1);

namespace App\Services;

use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

final class StripeService
{
    public const array WEBHOOK_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'charge.refunded',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'customer.updated',
        'customer.deleted',
        'invoice.payment_action_required',
        'invoice.payment_succeeded',
    ];

    public function configured(): bool
    {
        return filled(config('cashier.key')) && filled(config('cashier.secret')) && filled(config('cashier.webhook.secret'));
    }

    public function isLiveKey(string $key): bool
    {
        return str_contains($key, '_live_');
    }

    public function accountName(string $secretKey): string
    {
        $account = $this->client($secretKey)->accounts->retrieve();

        $name = $account->business_profile->name ?? $account->settings->dashboard->display_name ?? $account->email ?? null;

        return is_string($name) && $name !== '' ? $name : $account->id;
    }

    /**
     * @return array{id: string, secret: string}
     */
    public function createWebhookEndpoint(string $secretKey, string $url): array
    {
        $client = $this->client($secretKey);

        foreach ($client->webhookEndpoints->all(['limit' => 100])->data as $existing) {
            if ($existing->url === $url) {
                $client->webhookEndpoints->delete($existing->id);
            }
        }

        $endpoint = $client->webhookEndpoints->create([
            'url' => $url,
            'enabled_events' => self::WEBHOOK_EVENTS,
            'api_version' => Cashier::STRIPE_VERSION,
        ]);

        return ['id' => $endpoint->id, 'secret' => (string) $endpoint->secret];
    }

    public function deleteWebhookEndpoint(string $secretKey, string $endpointId): void
    {
        try {
            $this->client($secretKey)->webhookEndpoints->delete($endpointId);
        } catch (InvalidRequestException) {
            return;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(array $params): array
    {
        $session = $this->client(config()->string('cashier.secret'))->request('post', '/v1/checkout/sessions', $params, []);

        return ['id' => (string) data_get($session->toArray(), 'id'), 'url' => (string) data_get($session->toArray(), 'url')];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->client(config()->string('cashier.secret'))->checkout->sessions
            ->retrieve($sessionId, ['expand' => ['shipping_cost.shipping_rate']])
            ->toArray();
    }

    public function expireCheckoutSession(string $sessionId): void
    {
        $this->client(config()->string('cashier.secret'))->checkout->sessions->expire($sessionId);
    }

    private function client(string $secretKey): StripeClient
    {
        return Cashier::stripe(['api_key' => $secretKey]);
    }
}
