<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\ShopService;

function connectStripeForShop(): void
{
    config(['cashier.key' => 'pk_test_1', 'cashier.secret' => 'sk_test_1', 'cashier.webhook.secret' => 'whsec_1']);
}

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, mixed>  $attributes
 */
function shopRecord(array $data = ['current_price' => '19.99'], array $attributes = [], bool $sellable = true): Record
{
    $type = $sellable ? RecordType::factory()->sellable()->create() : RecordType::factory()->create();

    return Record::factory()->create([
        'record_type_id' => $type->id,
        'data' => $data,
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

it('sells online only once stripe is fully connected', function (): void {
    expect(resolve(ShopService::class)->sellsOnline())->toBeFalse();

    connectStripeForShop();

    expect(resolve(ShopService::class)->sellsOnline())->toBeTrue();
});

it('reads a positive price, including one stored per language', function (mixed $stored, ?string $expected): void {
    expect(resolve(ShopService::class)->price(shopRecord(['current_price' => $stored])))->toBe($expected);
})->with([
    'decimal' => ['19.99', '19.99'],
    'number' => [25, '25'],
    'per language' => [['en' => '12.50'], '12.50'],
    'zero' => ['0', null],
    'not a number' => ['free', null],
    'missing' => [null, null],
]);

it('maps the billing option to a one-time or recurring charge', function (?string $billing, string $expected, bool $subscription): void {
    $record = shopRecord(['current_price' => '5', 'billing' => $billing]);

    expect(resolve(ShopService::class)->billing($record))->toBe($expected)
        ->and(resolve(ShopService::class)->isSubscription($record))->toBe($subscription);
})->with([
    'monthly' => ['Monthly', ShopService::MONTHLY, true],
    'yearly' => [' yearly ', ShopService::YEARLY, true],
    'one-time' => ['One-time', ShopService::ONE_TIME, false],
    'unknown' => ['Weekly', ShopService::ONE_TIME, false],
    'unset' => [null, ShopService::ONE_TIME, false],
]);

it('knows whether a record ships and how much stock it has', function (): void {
    $shop = resolve(ShopService::class);
    $shipped = shopRecord(['current_price' => '5', 'shippable' => true], ['stock' => 3]);
    $digital = shopRecord(['current_price' => '5']);

    expect($shop->needsShipping($shipped))->toBeTrue()
        ->and($shop->needsShipping($digital))->toBeFalse()
        ->and($shop->tracksStock($shipped))->toBeTrue()
        ->and($shop->tracksStock($digital))->toBeFalse()
        ->and($shop->inStock($shipped, 3))->toBeTrue()
        ->and($shop->inStock($shipped, 4))->toBeFalse()
        ->and($shop->inStock($digital, 500))->toBeTrue()
        ->and($shop->isSellable($shipped))->toBeTrue();
});

it('makes a record purchasable only when every condition holds', function (Closure $record, bool $connected, bool $expected): void {
    if ($connected) {
        connectStripeForShop();
    }

    expect(resolve(ShopService::class)->isPurchasable($record()))->toBe($expected);
})->with([
    'purchasable' => [fn (): Record => shopRecord(), true, true],
    'stripe not connected' => [fn (): Record => shopRecord(), false, false],
    'type not sellable' => [fn (): Record => shopRecord(sellable: false), true, false],
    'unpublished' => [fn (): Record => shopRecord(attributes: ['status' => ContentStatus::DRAFT]), true, false],
    'no price' => [fn (): Record => shopRecord(['current_price' => '']), true, false],
    'sold out' => [fn (): Record => shopRecord(attributes: ['stock' => 0]), true, false],
]);
