<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Support\Number;
use ResourceBundle;

final readonly class ShopService
{
    public const string ONE_TIME = 'one-time';

    public const string MONTHLY = 'monthly';

    public const string YEARLY = 'yearly';

    public const int MAX_SHIPPING_RATES = 5;

    /**
     * @var array<int, string>
     */
    private const array UNSHIPPABLE_REGIONS = [
        'AS', 'CC', 'CP', 'CQ', 'CU', 'CX', 'DG', 'EA', 'EU', 'EZ', 'FM', 'HM', 'IC', 'IR', 'KP',
        'MH', 'MP', 'NF', 'PW', 'QO', 'SD', 'SY', 'UM', 'UN', 'VI', 'XA', 'XB', 'ZZ',
    ];

    public function __construct(private StripeService $stripe) {}

    public function sellsOnline(): bool
    {
        return $this->stripe->configured();
    }

    public function isOpen(): bool
    {
        return $this->sellsOnline() && once(fn (): bool => RecordType::query()->where('sellable', true)->exists());
    }

    public function toMinor(string $amount): int
    {
        return (int) round((float) $amount * 10 ** SettingsService::current()->currencyDecimals());
    }

    public function formatMinor(int $amount, ?string $currency = null): string
    {
        $code = $currency ?? SettingsService::current()->currency();
        $decimals = config()->integer('currencies.'.$code.'.decimals', 2);

        return config()->string('currencies.'.$code.'.symbol', $code).Number::format($amount / 10 ** $decimals, precision: $decimals);
    }

    /**
     * @return array<string, string>
     */
    public function countryOptions(): array
    {
        $bundle = ResourceBundle::create(app()->getLocale(), 'ICUDATA-region');
        $countries = [];

        foreach ($bundle?->get('Countries') ?? [] as $code => $name) {
            if (is_string($code) && is_string($name) && preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, self::UNSHIPPABLE_REGIONS, true)) {
                $countries[$code] = $name;
            }
        }

        asort($countries, SORT_LOCALE_STRING);

        return $countries;
    }

    /**
     * @return array<int, string>
     */
    public function shippingCountries(): array
    {
        $saved = config('site.shop_shipping_countries');
        $options = $this->countryOptions();

        return is_array($saved)
            ? array_values(array_filter($saved, fn (mixed $code): bool => is_string($code) && array_key_exists($code, $options)))
            : [];
    }

    /**
     * @return array<int, array{name: string, amount: string}>
     */
    public function shippingRates(): array
    {
        $saved = config('site.shop_shipping_rates');
        $rates = [];

        foreach (is_array($saved) ? $saved : [] as $rate) {
            if (is_array($rate) && is_string($rate['name'] ?? null) && $rate['name'] !== '' && is_numeric($rate['amount'] ?? null)) {
                $rates[] = ['name' => $rate['name'], 'amount' => (string) $rate['amount']];
            }
        }

        return array_slice($rates, 0, self::MAX_SHIPPING_RATES);
    }

    public function calculatesTax(): bool
    {
        return (bool) config('site.shop_automatic_tax', false);
    }

    public function pricesIncludeTax(): bool
    {
        return (bool) config('site.shop_prices_include_tax', false);
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
