<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NotFoundPath;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotFoundPath>
 */
final class NotFoundPathFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => '/'.fake()->unique()->slug(3),
            'hits' => 1,
            'referrer' => null,
            'last_seen_at' => now(),
        ];
    }
}
