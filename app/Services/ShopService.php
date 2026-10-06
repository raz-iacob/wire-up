<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Record;

final readonly class ShopService
{
    public const string ONE_TIME = 'one-time';

    public const string MONTHLY = 'monthly';

    public const string YEARLY = 'yearly';

    public function __construct(private StripeService $stripe) {}

    public function sellsOnline(): bool
    {
        return $this->stripe->configured();
    }

    public function isSellable(Record $record): bool
    {
        return $record->recordType->sellable;
    }

    public function price(Record $record): ?string
    {
        $price = $this->value($record, RecordTypePresets::PRICE_FIELD);

        return is_numeric($price) && (float) $price > 0 ? (string) $price : null;
    }

    public function billing(Record $record): string
    {
        return match (mb_strtolower(mb_trim((string) $this->value($record, RecordTypePresets::BILLING_FIELD)))) {
            self::MONTHLY => self::MONTHLY,
            self::YEARLY => self::YEARLY,
            default => self::ONE_TIME,
        };
    }

    public function isSubscription(Record $record): bool
    {
        return $this->billing($record) !== self::ONE_TIME;
    }

    public function needsShipping(Record $record): bool
    {
        return (bool) $this->value($record, RecordTypePresets::SHIPPABLE_FIELD);
    }

    public function tracksStock(Record $record): bool
    {
        return $record->stock !== null;
    }

    public function inStock(Record $record, int $quantity = 1): bool
    {
        return $record->stock === null || $record->stock >= $quantity;
    }

    public function isPurchasable(Record $record): bool
    {
        return $this->sellsOnline()
            && $this->isSellable($record)
            && $record->isLiveInLocale()
            && $this->price($record) !== null
            && $this->inStock($record);
    }

    private function value(Record $record, string $key): mixed
    {
        $value = $record->data[$key] ?? null;

        return is_array($value) ? ($value[app()->getLocale()] ?? null) : $value;
    }
}
