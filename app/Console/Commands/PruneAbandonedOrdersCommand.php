<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ReleaseOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Delete checkouts that were never paid once they are a week old')]
#[Signature('wireup:prune-abandoned-orders')]
final class PruneAbandonedOrdersCommand extends Command
{
    public function handle(ReleaseOrderAction $release): int
    {
        $orders = Order::query()
            ->whereNull('paid_at')
            ->whereIn('status', [OrderStatus::PENDING, OrderStatus::CANCELLED])
            ->where('created_at', '<', now()->subDays(7))
            ->get();

        foreach ($orders as $order) {
            $release->handle($order);
            $order->delete();
        }

        $this->components->info("Deleted {$orders->count()} abandoned checkouts.");

        return self::SUCCESS;
    }
}
