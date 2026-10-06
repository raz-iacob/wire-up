<?php

declare(strict_types=1);

use App\Services\StripeService;
use Stripe\Exception\AuthenticationException;

it('is configured only when the publishable key, secret key and webhook secret are all set', function (?string $key, ?string $secret, ?string $webhookSecret, bool $expected): void {
    config(['cashier.key' => $key, 'cashier.secret' => $secret, 'cashier.webhook.secret' => $webhookSecret]);

    expect(resolve(StripeService::class)->configured())->toBe($expected);
})->with([
    'all set' => ['pk_test_1', 'sk_test_1', 'whsec_1', true],
    'no publishable key' => [null, 'sk_test_1', 'whsec_1', false],
    'no secret key' => ['pk_test_1', null, 'whsec_1', false],
    'no webhook secret' => ['pk_test_1', 'sk_test_1', null, false],
]);

it('names the account by its business name, dashboard name, email or id', function (array $account, string $expected): void {
    stripe()->respond('GET', '/v1/account', ['id' => 'acct_123', 'object' => 'account', ...$account]);

    expect(resolve(StripeService::class)->accountName('sk_test_1'))->toBe($expected);
})->with([
    'business name' => [['business_profile' => ['name' => 'Acme Studio'], 'email' => 'owner@example.com'], 'Acme Studio'],
    'dashboard name' => [['business_profile' => ['name' => null], 'settings' => ['dashboard' => ['display_name' => 'Acme']]], 'Acme'],
    'email' => [['email' => 'owner@example.com'], 'owner@example.com'],
    'id' => [['email' => ''], 'acct_123'],
]);

it('ignores a webhook endpoint that stripe no longer has', function (): void {
    stripe()->fail('DELETE', '/v1/webhook_endpoints/we_gone', 'No such webhook endpoint', 404);

    resolve(StripeService::class)->deleteWebhookEndpoint('sk_test_1', 'we_gone');

    stripe()->assertSent('DELETE', '/v1/webhook_endpoints/we_gone');
});

it('surfaces other errors when deleting a webhook endpoint', function (): void {
    stripe()->fail('DELETE', '/v1/webhook_endpoints/we_1', 'Invalid API Key provided', 401);

    resolve(StripeService::class)->deleteWebhookEndpoint('sk_test_bad', 'we_1');
})->throws(AuthenticationException::class);
