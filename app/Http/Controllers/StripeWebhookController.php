<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
}
