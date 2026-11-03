<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ErrorLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErrorLog>
 */
final class ErrorLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'type' => 'RuntimeException',
            'message' => fake()->sentence(),
            'file' => 'app/Services/ExampleService.php',
            'line' => fake()->numberBetween(1, 500),
            'address' => 'GET https://example.com/'.fake()->slug(2),
            'user_id' => null,
            'trace' => ['app/Services/ExampleService.php:12 App\Services\ExampleService->run()'],
            'context' => ['version' => 'v0.6.0', 'php' => PHP_VERSION, 'environment' => 'production'],
            'occurrences' => 1,
            'last_seen_at' => now(),
            'resolved_at' => null,
            'resolved_by' => null,
        ];
    }

    public function resolved(): self
    {
        return $this->state(fn (): array => ['resolved_at' => now(), 'resolved_by' => null]);
    }
}
