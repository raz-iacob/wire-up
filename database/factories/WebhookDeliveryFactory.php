<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
final class WebhookDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->unique()->uuid(),
            'webhook_id' => Webhook::factory(),
            'event' => WebhookEvent::SUBMISSION_CREATED->value,
            'payload' => ['event' => WebhookEvent::SUBMISSION_CREATED->value, 'data' => []],
            'status_code' => null,
            'response_excerpt' => null,
            'error' => null,
            'attempts' => 0,
            'delivered_at' => null,
        ];
    }

    public function delivered(): self
    {
        return $this->state(fn (): array => ['status_code' => 200, 'attempts' => 1, 'delivered_at' => now()]);
    }

    public function failed(string $error = 'Connection refused'): self
    {
        return $this->state(fn (): array => ['error' => $error, 'attempts' => 5]);
    }
}
