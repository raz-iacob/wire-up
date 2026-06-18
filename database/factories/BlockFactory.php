<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Block;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Block>
 */
final class BlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blockable_id' => fn () => Page::factory()->create()->id,
            'blockable_type' => 'page',
            'type' => 'spacer',
            'position' => 0,
            'content' => ['size' => 'medium'],
        ];
    }
}
