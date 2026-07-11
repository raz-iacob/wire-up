<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Services\RolePresets;
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
            'role_id' => Role::factory(),
            'active' => fake()->boolean(),
            'locale' => fake()->languageCode(),
            'last_seen_at' => fake()->optional(0.7)->dateTimeBetween('-1 year', 'now'),
            'user_agent' => fake()->userAgent(),
            'last_ip' => fake()->ipv4(),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): self
    {
        return $this->afterMaking(function (User $user): void {
            $key = $user->getAttributes()['role'] ?? null;

            if (is_string($key)) {
                $user->offsetUnset('role');
                $user->setAttribute('role_id', $this->roleId($key));
            }
        });
    }

    public function unverified(): self
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function role(Role $role): self
    {
        return $this->for($role);
    }

    public function owner(): self
    {
        return $this->preset('owner');
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

    private function roleId(string $key): int
    {
        $preset = RolePresets::find($key);

        return Role::query()->updateOrCreate(
            ['key' => $key],
            [
                'name' => $preset['name'],
                'abilities' => $preset['abilities'],
                'bypass' => $preset['bypass'],
                'is_protected' => $preset['is_protected'],
            ],
        )->id;
    }

    private function preset(string $key): self
    {
        return $this->state(fn (array $attributes): array => [
            'role_id' => $this->roleId($key),
        ]);
    }
}
