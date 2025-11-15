<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Locale>
 */
final class LocaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('??????'),
            'name' => fake()->word(),
            'endonym' => fake()->optional()->word(),
            'script' => fake()->optional()->randomElement(['Latin', 'Arabic', 'Han', 'Cyrillic']),
            'rtl' => fake()->boolean(20),
            'active' => fake()->boolean(70),
            'published' => fake()->boolean(60),
        ];
    }
}
