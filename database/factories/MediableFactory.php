<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locale;
use App\Models\Media;
use App\Models\Mediable;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mediable>
 */
final class MediableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $page = Page::factory()->create();

        return [
            'media_id' => Media::factory(),
            'mediable_id' => $page->id,
            'mediable_type' => $page->getMorphClass(),
            'locale' => fn () => Locale::query()->inRandomOrder()->first()->code,
            'role' => $this->faker->randomElement(['poster', 'banner', 'thumbnail']),
            'crop' => [
                'desktop' => [
                    'x' => $this->faker->numberBetween(0, 100),
                    'y' => $this->faker->numberBetween(0, 100),
                    'w' => $this->faker->numberBetween(100, 800),
                    'h' => $this->faker->numberBetween(100, 600),
                ],
                'mobile' => [
                    'x' => $this->faker->numberBetween(0, 100),
                    'y' => $this->faker->numberBetween(0, 100),
                    'w' => $this->faker->numberBetween(100, 800),
                    'h' => $this->faker->numberBetween(100, 600),
                ],
            ],
            'metadata' => null,
            'position' => $this->faker->numberBetween(0, 10),
            'published' => $this->faker->boolean(80),
        ];
    }
}
