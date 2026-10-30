<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RedirectOrigin;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
final class RedirectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from' => '/'.fake()->unique()->slug(3),
            'to' => '/'.fake()->slug(2),
            'status_code' => 301,
            'origin' => RedirectOrigin::MANUAL,
            'hits' => 0,
            'last_hit_at' => null,
        ];
    }
}
