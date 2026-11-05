<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookEvent: string
{
    case SUBMISSION_CREATED = 'submission.created';
    case NEWSLETTER_SUBSCRIBED = 'newsletter.subscribed';
    case ORDER_PAID = 'order.paid';
    case ORDER_FULFILLED = 'order.fulfilled';
    case ORDER_REFUNDED = 'order.refunded';
    case ORDER_CANCELLED = 'order.cancelled';
    case PING = 'ping';

    /**
     * @return list<self>
     */
    public static function subscribable(): array
    {
        return [
            self::SUBMISSION_CREATED,
            self::NEWSLETTER_SUBSCRIBED,
            self::ORDER_PAID,
            self::ORDER_FULFILLED,
            self::ORDER_REFUNDED,
            self::ORDER_CANCELLED,
        ];
    }

    public static function forOrderStatus(OrderStatus $status): ?self
    {
        return match ($status) {
            OrderStatus::PAID => self::ORDER_PAID,
            OrderStatus::FULFILLED => self::ORDER_FULFILLED,
            OrderStatus::REFUNDED => self::ORDER_REFUNDED,
            OrderStatus::CANCELLED => self::ORDER_CANCELLED,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SUBMISSION_CREATED => __('Form submitted'),
            self::NEWSLETTER_SUBSCRIBED => __('Newsletter sign-up'),
            self::ORDER_PAID => __('Order paid'),
            self::ORDER_FULFILLED => __('Order fulfilled'),
            self::ORDER_REFUNDED => __('Order refunded'),
            self::ORDER_CANCELLED => __('Order cancelled'),
            self::PING => __('Test'),
        };
    }
}
