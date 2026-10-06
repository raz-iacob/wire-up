<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

it('belongs to the customer who placed it and holds its items', function (): void {
    $user = User::factory()->member()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $item = OrderItem::factory()->for($order)->create();

    expect($order->user?->is($user))->toBeTrue()
        ->and($order->items->sole()->is($item))->toBeTrue()
        ->and($item->order->is($order))->toBeTrue();
});

it('keeps the order when its customer is deleted', function (): void {
    $user = User::factory()->member()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect($order->refresh()->user_id)->toBeNull();
});
