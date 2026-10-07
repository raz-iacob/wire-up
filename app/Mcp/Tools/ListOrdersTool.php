<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Contracts\HiddenFromAssistant;
use App\Enums\OrderStatus;
use App\Mcp\Support\Orders;
use App\Mcp\Support\Pages;
use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list-orders')]
#[Description('List shop orders (newest first) with their reference, status, customer and total. Read-only.')]
final class ListOrdersTool extends Tool implements HiddenFromAssistant
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'status' => ['sometimes', 'string', Rule::enum(OrderStatus::class)],
                'search' => ['sometimes', 'string', 'max:255'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ],
            ['status.enum' => 'Status must be one of: '.implode(', ', array_column(OrderStatus::cases(), 'value')).'.'],
        );

        $orders = Order::query()
            ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', $validated['status']))
            ->when(isset($validated['search']), fn (Builder $query): Builder => $query->whereAny(['reference', 'email', 'name'], 'like', '%'.$validated['search'].'%'))
            ->latest('id')
            ->limit((int) ($validated['limit'] ?? 25))
            ->get()
            ->map(Orders::summary(...));

        return Pages::json(['orders' => $orders->all()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_column(OrderStatus::cases(), 'value'))
                ->description('Only return orders with this status. "paid" orders are waiting to be fulfilled.'),

            'search' => $schema->string()
                ->description('Match part of the order reference, customer email or customer name.'),

            'limit' => $schema->integer()
                ->description('Maximum number of orders to return (default 25, max 100).')
                ->default(25),
        ];
    }
}
