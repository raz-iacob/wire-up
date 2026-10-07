<?php

declare(strict_types=1);

use App\Ai\Agents\SiteAssistant;
use App\Enums\OrderStatus;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\ListOrdersTool;
use App\Models\Order;
use App\Models\OrderItem;

it('lists orders newest first, filtered by status or a search', function (): void {
    Order::factory()->paid()->create(['reference' => 'OLDPAID1', 'email' => 'old@example.com']);
    Order::factory()->paid()->create(['reference' => 'NEWPAID1', 'email' => 'new@example.com', 'total_amount' => 4250]);
    Order::factory()->create(['reference' => 'PENDING1']);

    WireUpServer::tool(ListOrdersTool::class, [])
        ->assertOk()
        ->assertSee(['"reference":"PENDING1"', '"reference":"NEWPAID1"', '"total_minor":4250']);

    WireUpServer::tool(ListOrdersTool::class, ['status' => 'paid', 'search' => 'new@', 'limit' => 5])
        ->assertOk()
        ->assertSee('"reference":"NEWPAID1"')
        ->assertDontSee('OLDPAID1')
        ->assertDontSee('PENDING1');
});

it('rejects an unknown order status', function (): void {
    WireUpServer::tool(ListOrdersTool::class, ['status' => 'shipped'])
        ->assertHasErrors(['Status must be one of: pending, processing, paid, fulfilled, cancelled, refunded.']);
});

it('returns one order with its items, totals and address', function (): void {
    $order = Order::factory()->paid()->create([
        'reference' => 'DETAIL01',
        'status' => OrderStatus::FULFILLED,
        'shipping_amount' => 500,
        'shipping_method' => 'Standard delivery',
        'shipping_address' => ['name' => 'Ada Shopper', 'address' => ['city' => 'Toronto']],
        'fulfilled_at' => now(),
    ]);
    OrderItem::factory()->for($order)->create(['name' => 'Walnut lamp', 'sku' => 'LAMP-1']);

    WireUpServer::tool(GetOrderTool::class, ['reference' => ' detail01 '])
        ->assertOk()
        ->assertSee(['"status":"fulfilled"', '"name":"Walnut lamp"', '"sku":"LAMP-1"', '"city":"Toronto"', '"shipping_method":"Standard delivery"'])
        ->assertSee('"admin_url":"'.route('admin.orders-show', $order).'"');
});

it('explains when an order reference is missing or unknown', function (): void {
    WireUpServer::tool(GetOrderTool::class, [])
        ->assertHasErrors(['Pass the order reference. Use list-orders to find it.']);

    WireUpServer::tool(GetOrderTool::class, ['reference' => 'NOPE0000'])
        ->assertHasErrors(['No order with reference "NOPE0000".']);
});

it('keeps the order tools away from the in-admin assistant', function (): void {
    $visible = SiteAssistant::visible(WireUpServer::toolClasses())->all();

    expect(WireUpServer::toolClasses())->toContain(ListOrdersTool::class, GetOrderTool::class)
        ->and($visible)->not->toContain(ListOrdersTool::class)
        ->and($visible)->not->toContain(GetOrderTool::class);
});

it('advertises the order tools with their names and required schema', function (): void {
    $list = resolve(ListOrdersTool::class)->toArray();
    $get = resolve(GetOrderTool::class)->toArray();

    expect($list['name'])->toBe('list-orders')
        ->and($list['inputSchema']['properties']['status']['enum'])->toContain('paid', 'refunded')
        ->and($get['name'])->toBe('get-order')
        ->and($get['inputSchema']['required'])->toBe(['reference']);
});
