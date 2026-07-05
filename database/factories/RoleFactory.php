<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Services\RolePresets;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->unique()->jobTitle(),
            'abilities' => [],
            'bypass' => false,
            'is_protected' => false,
        ];
    }

    public function owner(): self
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Owner',
            'bypass' => true,
            'is_protected' => true,
        ]);
    }

    public function admin(): self
    {
        return $this->preset('admin');
    }

    public function editor(): self
    {
        return $this->preset('editor');
    }

    public function author(): self
    {
        return $this->preset('author');
    }

    public function member(): self
    {
        return $this->preset('member');
    }

    private function preset(string $key): self
    {
        return $this->state(function (array $attributes) use ($key): array {
            $preset = RolePresets::find($key);

            return [
                'name' => $preset['name'],
                'abilities' => $preset['abilities'],
                'bypass' => $preset['bypass'],
                'is_protected' => $preset['is_protected'],
            ];
        });
    }
}
