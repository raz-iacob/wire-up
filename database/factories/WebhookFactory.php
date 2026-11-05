<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Webhook>
 */
final class WebhookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Zapier',
            'url' => 'https://hooks.example.com/'.fake()->unique()->slug(2),
            'secret' => Webhook::generateSecret(),
            'events' => [WebhookEvent::SUBMISSION_CREATED->value],
            'active' => true,
            'last_status' => null,
            'last_error' => null,
            'last_delivered_at' => null,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    /**
     * @param  list<WebhookEvent>  $events
     */
    public function listeningTo(array $events): self
    {
        return $this->state(fn (): array => ['events' => array_map(fn (WebhookEvent $event): string => $event->value, $events)]);
    }
}
