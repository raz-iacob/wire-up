<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;

final readonly class MarkOrderFulfilledAction
{
    public function handle(Order $order): bool
    {
        if ($order->status !== OrderStatus::PAID) {
            return false;
        }

        $order->update(['status' => OrderStatus::FULFILLED, 'fulfilled_at' => now()]);

        return true;
    }
}
