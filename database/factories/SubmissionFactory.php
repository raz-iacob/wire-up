<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Block;
use App\Models\Page;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
final class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'block_id' => Block::factory(),
            'type' => 'contact',
            'form_name' => null,
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => null,
            'subject' => null,
            'message' => $this->faker->sentence(),
            'metadata' => [],
            'ip' => $this->faker->ipv4(),
            'locale' => 'en',
        ];
    }
}
