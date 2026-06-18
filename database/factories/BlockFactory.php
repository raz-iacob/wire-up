<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BlockType;
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
            'type' => BlockType::SPACER,
            'position' => 0,
            'content' => BlockType::SPACER->defaultContent(),
        ];
    }
}
