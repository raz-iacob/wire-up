<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locale;
use App\Models\Page;
use App\Models\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Slug>
 */
final class SlugFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->words(3, true)),
            'locale' => fn () => Locale::query()->inRandomOrder()->first()->code,
            'sluggable_id' => fn () => Page::factory()->create()->id,
            'sluggable_type' => Page::class,
        ];
    }
}
