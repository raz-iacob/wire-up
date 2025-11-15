<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    private static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'photo' => fake()->optional(0.3)->randomElement(['avatars/user-1.jpg', 'avatars/user-2.jpg', 'avatars/user-3.jpg']),
            'stripe_id' => fake()->uuid(),
            'metadata' => [
                'phone' => fake()->phoneNumber(),
                'address' => fake()->address(),
                'birthday' => fake()->date(),
            ],
            'admin' => fake()->boolean(),
            'active' => fake()->boolean(),
            'locale' => fake()->languageCode(),
            'last_seen_at' => fake()->optional(0.7)->dateTimeBetween('-1 year', 'now'),
            'user_agent' => fake()->userAgent(),
            'last_ip' => fake()->ipv4(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): self
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
