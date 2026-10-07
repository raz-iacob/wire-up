<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Contracts\HiddenFromAssistant;
use App\Mcp\Support\Orders;
use App\Mcp\Support\Pages;
use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-order')]
#[Description('Get one shop order by its reference: the items bought, what Stripe charged, the shipping address and its status. Read-only.')]
final class GetOrderTool extends Tool implements HiddenFromAssistant
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['reference' => ['required', 'string', 'max:255']],
            ['reference.required' => 'Pass the order reference. Use list-orders to find it.'],
        );

        $order = Order::query()->with('items')->where('reference', mb_strtoupper(mb_trim((string) $validated['reference'])))->first();

        if (! $order instanceof Order) {
            return Response::error("No order with reference \"{$validated['reference']}\". Use list-orders to see the orders.");
        }

        return Pages::json(['order' => Orders::detail($order)]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()
                ->description('The order reference, as shown in list-orders or the admin (for example "K7M2QX9A").')
                ->required(),
        ];
    }
}
