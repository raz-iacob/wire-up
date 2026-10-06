<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Record;
use Illuminate\Support\Facades\DB;

final readonly class ReleaseOrderAction
{
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->status->holdsStock()) {
                return;
            }

            foreach ($locked->items()->where('reserved_stock', '>', 0)->whereNotNull('record_id')->get() as $item) {
                Record::query()->whereKey($item->record_id)->whereNotNull('stock')->increment('stock', $item->reserved_stock);
            }

            $locked->items()->update(['reserved_stock' => 0]);
            $locked->update(['status' => OrderStatus::CANCELLED, 'cancelled_at' => now()]);
        });
    }
}
