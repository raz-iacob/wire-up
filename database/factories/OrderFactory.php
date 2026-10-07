<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => Str::upper(Str::random(8)),
            'status' => OrderStatus::PENDING,
            'user_id' => null,
            'email' => null,
            'name' => null,
            'currency' => 'USD',
            'subtotal_amount' => 2000,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 2000,
            'refunded_amount' => 0,
            'shipping_address' => null,
            'shipping_method' => null,
            'stripe_session_id' => 'cs_test_'.Str::random(24),
            'stripe_payment_intent' => null,
            'locale' => 'en',
            'oversold' => false,
            'expires_at' => now()->addMinutes(35),
            'paid_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'restocked_at' => null,
        ];
    }

    public function paid(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::PAID,
            'email' => 'buyer@example.com',
            'name' => 'Sam Buyer',
            'stripe_payment_intent' => 'pi_'.Str::random(24),
            'paid_at' => now(),
        ]);
    }
}
