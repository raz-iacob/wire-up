<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecordType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecordType>
 */
final class RecordTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'key' => $word,
            'slug_prefix' => Str::slug(Str::plural($word)),
            'icon' => 'rectangle-stack',
            'name' => Str::title(Str::plural($word)),
            'fields' => [],
            'position' => 0,
        ];
    }
}
