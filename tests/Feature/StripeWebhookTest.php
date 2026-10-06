<?php

declare(strict_types=1);

it('refuses every webhook while no signing secret is configured', function (): void {
    config(['cashier.webhook.secret' => null]);

    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1'], 'whsec_anything')
        ->assertForbidden();
});

it('rejects a webhook signed with the wrong secret', function (): void {
    config(['cashier.webhook.secret' => 'whsec_real']);

    stripeWebhook('checkout.session.completed', ['id' => 'cs_test_1'], 'whsec_forged')
        ->assertForbidden();
});

it('accepts a correctly signed webhook for an event it does not handle', function (): void {
    config(['cashier.webhook.secret' => 'whsec_real']);

    stripeWebhook('product.created', ['id' => 'prod_1'])
        ->assertOk();
});
