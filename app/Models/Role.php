<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $key
 * @property-read string $name
 * @property-read array<int, string> $abilities
 * @property-read bool $bypass
 * @property-read bool $is_protected
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, User> $users
 */
final class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'abilities' => 'array',
            'bypass' => 'boolean',
            'is_protected' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasAbility(string $ability): bool
    {
        return $this->bypass || in_array($ability, $this->abilities, true);
    }

    public function canAccessAdmin(): bool
    {
        return $this->bypass || $this->abilities !== [];
    }

    public function isInUse(): bool
    {
        return $this->users()->exists();
    }
}
