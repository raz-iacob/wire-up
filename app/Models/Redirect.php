<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\RedirectOrigin;
use App\Services\ActivityLog;
use App\Services\RedirectService;
use Carbon\CarbonInterface;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $from
 * @property-read string $to
 * @property-read int $status_code
 * @property-read RedirectOrigin $origin
 * @property-read int $hits
 * @property-read CarbonInterface|null $last_hit_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'status_code' => 'integer',
            'origin' => RedirectOrigin::class,
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::saved(RedirectService::flush(...));
        self::deleted(function (self $redirect): void {
            RedirectService::flush();

            ActivityLog::record(ActivityEvent::REDIRECT_DELETED, $redirect);
        });
    }
}
