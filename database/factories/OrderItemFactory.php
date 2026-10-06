<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
final class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'record_id' => null,
            'name' => 'Walnut desk lamp',
            'sku' => null,
            'unit_amount' => 1000,
            'quantity' => 2,
            'line_amount' => 2000,
            'reserved_stock' => 0,
        ];
    }
}
