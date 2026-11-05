<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookEvent;
use Carbon\CarbonInterface;
use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $url
 * @property-read string $secret
 * @property-read list<string> $events
 * @property-read bool $active
 * @property-read int|null $last_status
 * @property-read string|null $last_error
 * @property-read CarbonInterface|null $last_delivered_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use HasFactory;

    public const int DELIVERIES_KEPT = 50;

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'secret' => 'encrypted',
            'events' => 'array',
            'active' => 'boolean',
            'last_status' => 'integer',
            'last_delivered_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest('id');
    }

    public function subscribesTo(WebhookEvent $event): bool
    {
        return $event === WebhookEvent::PING || in_array($event->value, $this->events, true);
    }
}
