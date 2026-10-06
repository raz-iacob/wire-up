<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Services\ShopService;

it('sells online only once stripe is fully connected', function (): void {
    expect(resolve(ShopService::class)->sellsOnline())->toBeFalse();

    connectStripe();

    expect(resolve(ShopService::class)->sellsOnline())->toBeTrue();
});

it('reads a positive price, including one stored per language', function (mixed $stored, ?string $expected): void {
    expect(resolve(ShopService::class)->price(sellableRecord(['current_price' => $stored])))->toBe($expected);
})->with([
    'decimal' => ['19.99', '19.99'],
    'number' => [25, '25'],
    'per language' => [['en' => '12.50'], '12.50'],
    'zero' => ['0', null],
    'not a number' => ['free', null],
    'missing' => [null, null],
]);

it('maps the billing option to a one-time or recurring charge', function (?string $billing, string $expected, bool $subscription): void {
    $record = sellableRecord(['current_price' => '5', 'billing' => $billing]);

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
    $shipped = sellableRecord(['current_price' => '5', 'shippable' => true], ['stock' => 3]);
    $digital = sellableRecord(['current_price' => '5']);

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
        connectStripe();
    }

    expect(resolve(ShopService::class)->isPurchasable($record()))->toBe($expected);
})->with([
    'purchasable' => [fn (): Record => sellableRecord(), true, true],
    'stripe not connected' => [fn (): Record => sellableRecord(), false, false],
    'type not sellable' => [fn (): Record => sellableRecord(sellable: false), true, false],
    'unpublished' => [fn (): Record => sellableRecord(attributes: ['status' => ContentStatus::DRAFT]), true, false],
    'no price' => [fn (): Record => sellableRecord(['current_price' => '']), true, false],
    'sold out' => [fn (): Record => sellableRecord(attributes: ['stock' => 0]), true, false],
]);
