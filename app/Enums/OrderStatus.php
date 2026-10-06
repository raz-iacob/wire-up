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
