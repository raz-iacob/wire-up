<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;

it('deletes week-old checkouts that were never paid and keeps everything else', function (): void {
    $stale = ['created_at' => now()->subDays(8)];
    $abandoned = Order::factory()->create([...$stale, 'status' => OrderStatus::CANCELLED]);
    $stuck = Order::factory()->create([...$stale, 'status' => OrderStatus::PENDING]);
    $recent = Order::factory()->create(['status' => OrderStatus::CANCELLED, 'created_at' => now()->subDays(6)]);
    $paidThenRefunded = Order::factory()->paid()->create([...$stale, 'status' => OrderStatus::REFUNDED]);
    $paidThenCancelled = Order::factory()->paid()->create([...$stale, 'status' => OrderStatus::CANCELLED]);

    $record = sellableRecord(attributes: ['stock' => 1]);
    OrderItem::factory()->for($stuck)->create(['record_id' => $record->id, 'quantity' => 2, 'reserved_stock' => 2]);

    $this->artisan('wireup:prune-abandoned-orders')
        ->expectsOutputToContain('Deleted 2 abandoned checkouts.')
        ->assertSuccessful();

    $this->assertModelMissing($abandoned);
    $this->assertModelMissing($stuck);
    $this->assertModelExists($recent);
    $this->assertModelExists($paidThenRefunded);
    $this->assertModelExists($paidThenCancelled);

    expect($record->refresh()->stock)->toBe(3)
        ->and(OrderItem::query()->where('order_id', $stuck->id)->exists())->toBeFalse();
});
