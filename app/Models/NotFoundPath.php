<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\NotFoundPathFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $path
 * @property-read int $hits
 * @property-read string|null $referrer
 * @property-read CarbonInterface $last_seen_at
 */
final class NotFoundPath extends Model
{
    /** @use HasFactory<NotFoundPathFactory> */
    use HasFactory, MassPrunable;

    public const null CREATED_AT = null;

    public const string UPDATED_AT = 'last_seen_at';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'hits' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('last_seen_at', '<', now()->subDays(30));
    }
}
