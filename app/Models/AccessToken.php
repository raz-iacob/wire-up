<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TokenAccess;
use Carbon\CarbonInterface;
use Database\Factories\AccessTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string $name
 * @property-read string $token_hash
 * @property-read TokenAccess $access
 * @property-read CarbonInterface $expires_at
 * @property-read CarbonInterface|null $last_used_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 */
final class AccessToken extends Model
{
    /** @use HasFactory<AccessTokenFactory> */
    use HasFactory;

    public const string PREFIX = 'wu_';

    public const string REQUEST_ATTRIBUTE = 'mcp.token';

    public static function generate(): string
    {
        return self::PREFIX.Str::random(48);
    }

    public static function hash(string $plainText): string
    {
        return hash('sha256', $plainText);
    }

    public static function findByPlainText(string $plainText): ?self
    {
        if (! str_starts_with($plainText, self::PREFIX)) {
            return null;
        }

        return self::query()->with('user.role')->where('token_hash', self::hash($plainText))->first();
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'access' => TokenAccess::class,
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
