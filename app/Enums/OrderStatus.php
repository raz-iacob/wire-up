<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FULFILLED = 'fulfilled';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Started'),
            self::PROCESSING => __('Payment processing'),
            self::PAID => __('Paid'),
            self::FULFILLED => __('Fulfilled'),
            self::CANCELLED => __('Cancelled'),
            self::REFUNDED => __('Refunded'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING, self::PROCESSING => 'amber',
            self::PAID => 'green',
            self::FULFILLED => 'blue',
            self::CANCELLED => 'zinc',
            self::REFUNDED => 'red',
        };
    }

    public function holdsStock(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING], true);
    }

    public function canBecomePaid(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING, self::CANCELLED], true);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this, [self::PAID, self::FULFILLED], true);
    }
}
