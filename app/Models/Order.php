<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $reference
 * @property-read OrderStatus $status
 * @property-read int|null $user_id
 * @property-read string|null $email
 * @property-read string|null $name
 * @property-read string $currency
 * @property-read int $subtotal_amount
 * @property-read int $shipping_amount
 * @property-read int $tax_amount
 * @property-read int $discount_amount
 * @property-read int $total_amount
 * @property-read int $refunded_amount
 * @property-read array<string, mixed>|null $shipping_address
 * @property-read string|null $shipping_method
 * @property-read string|null $stripe_session_id
 * @property-read string|null $stripe_payment_intent
 * @property-read string $locale
 * @property-read bool $oversold
 * @property-read CarbonInterface|null $expires_at
 * @property-read CarbonInterface|null $paid_at
 * @property-read CarbonInterface|null $fulfilled_at
 * @property-read CarbonInterface|null $cancelled_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, OrderItem> $items
 */
final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public static function awaitingFulfilmentCount(): int
    {
        return once(fn (): int => self::query()->where('status', OrderStatus::PAID)->count());
    }

    public function stripeDashboardUrl(): ?string
    {
        if ($this->stripe_payment_intent === null) {
            return null;
        }

        $secret = config('cashier.secret');
        $mode = is_string($secret) && str_contains($secret, '_test_') ? 'test/' : '';

        return 'https://dashboard.stripe.com/'.$mode.'payments/'.$this->stripe_payment_intent;
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'status' => OrderStatus::class,
            'user_id' => 'integer',
            'subtotal_amount' => 'integer',
            'shipping_amount' => 'integer',
            'tax_amount' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
            'refunded_amount' => 'integer',
            'shipping_address' => 'array',
            'oversold' => 'boolean',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
