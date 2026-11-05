<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read int $webhook_id
 * @property-read string $event
 * @property-read array<string, mixed> $payload
 * @property-read int|null $status_code
 * @property-read string|null $response_excerpt
 * @property-read string|null $error
 * @property-read int $attempts
 * @property-read CarbonInterface|null $delivered_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Webhook $webhook
 */
final class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'webhook_id' => 'integer',
            'payload' => 'array',
            'status_code' => 'integer',
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function succeeded(): bool
    {
        return $this->delivered_at !== null;
    }
}
