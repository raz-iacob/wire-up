<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\ActivityLog;

final readonly class MarkOrderFulfilledAction
{
    public function handle(Order $order): bool
    {
        if ($order->status !== OrderStatus::PAID) {
            return false;
        }

        $order->update(['status' => OrderStatus::FULFILLED, 'fulfilled_at' => now()]);

        ActivityLog::record(ActivityEvent::ORDER_FULFILLED, $order);

        return true;
    }
}
