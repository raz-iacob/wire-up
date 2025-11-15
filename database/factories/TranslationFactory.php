<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locale;
use App\Models\Page;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Translation>
 */
final class TranslationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->word(),
            'body' => $this->faker->sentence(),
            'locale' => fn () => Locale::query()->inRandomOrder()->first()->code ?? 'en',
            'translatable_id' => fn () => Page::factory()->create()->id,
            'translatable_type' => Page::class,
        ];
    }
}
