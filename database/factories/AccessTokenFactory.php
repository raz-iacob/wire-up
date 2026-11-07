<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TokenAccess;
use App\Models\AccessToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessToken>
 */
final class AccessTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Claude Code',
            'token_hash' => AccessToken::hash(AccessToken::generate()),
            'access' => TokenAccess::Edit,
            'expires_at' => now()->addDays(90),
            'last_used_at' => null,
        ];
    }

    public function plainText(string $plainText): self
    {
        return $this->state(fn (): array => ['token_hash' => AccessToken::hash($plainText)]);
    }

    public function access(TokenAccess $access): self
    {
        return $this->state(fn (): array => ['access' => $access]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
