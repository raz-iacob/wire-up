<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $email
 * @property-read CarbonInterface|null $email_verified_at
 * @property-read string $password
 * @property-read string|null $photo
 * @property-read string|null $stripe_id
 * @property-read array<string, mixed>|null $metadata
 * @property-read int|null $role_id
 * @property-read Role|null $role
 * @property-read bool $active
 * @property-read string $locale
 * @property-read CarbonInterface|null $last_seen_at
 * @property-read string|null $user_agent
 * @property-read string|null $last_ip
 * @property-read string|null $remember_token
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read string $initials
 * @property-read string|null $photo_url
 */
#[Hidden([
    'password',
    'remember_token',
    'stripe_id',
])]
final class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'email' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'photo' => 'string',
            'stripe_id' => 'string',
            'metadata' => 'json',
            'role_id' => 'integer',
            'active' => 'boolean',
            'locale' => 'string',
            'last_seen_at' => 'datetime',
            'user_agent' => 'string',
            'last_ip' => 'string',
            'remember_token' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function canAccessAdmin(): bool
    {
        return $this->role?->canAccessAdmin() ?? false;
    }

    public function hasAbility(string $ability): bool
    {
        return $this->role?->hasAbility($ability) ?? false;
    }

    /**
     * @return Attribute<string, null>
     */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Str::of($this->name)
                ->explode(' ')
                ->take(2)
                ->map(fn (string $word): string => Str::substr($word, 0, 1))
                ->implode(''),
        );
    }

    /**
     * @return Attribute<string|null, null>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->photo
                ? Storage::disk(config('filesystems.media'))->url($this->photo)
                : null,
        );
    }
}
