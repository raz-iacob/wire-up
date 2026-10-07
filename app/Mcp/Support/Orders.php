<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ShopService;

final class Orders
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Order $order): array
    {
        $shop = resolve(ShopService::class);

        return [
            'reference' => $order->reference,
            'status' => $order->status->value,
            'customer_name' => $order->name,
            'customer_email' => $order->email,
            'currency' => $order->currency,
            'total' => $shop->formatMinor($order->total_amount, $order->currency),
            'total_minor' => $order->total_amount,
            'oversold' => $order->oversold,
            'placed_at' => $order->created_at->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Order $order): array
    {
        $shop = resolve(ShopService::class);
        $money = fn (int $amount): string => $shop->formatMinor($amount, $order->currency);

        return [
            ...self::summary($order),
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $money($item->unit_amount),
                'line_total' => $money($item->line_amount),
                'record_id' => $item->record_id,
            ])->all(),
            'subtotal' => $money($order->subtotal_amount),
            'shipping' => $money($order->shipping_amount),
            'shipping_method' => $order->shipping_method,
            'tax' => $money($order->tax_amount),
            'discount' => $money($order->discount_amount),
            'refunded' => $money($order->refunded_amount),
            'shipping_address' => $order->shipping_address,
            'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'stripe_payment_url' => $order->stripeDashboardUrl(),
            'admin_url' => route('admin.orders-show', $order),
        ];
    }
}
