<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Record;

final readonly class CartService
{
    public const string SESSION_KEY = 'cart';

    public const int MAX_QUANTITY = 99;

    public function __construct(private ShopService $shop) {}

    public function add(Record $record, int $quantity): bool
    {
        if (! $this->accepts($record)) {
            return false;
        }

        $cart = $this->stored();
        $cart[$record->id] = $this->clamp($record, ($cart[$record->id] ?? 0) + $quantity);

        $this->store($cart);

        return true;
    }

    public function update(int $recordId, int $quantity): void
    {
        $cart = $this->stored();

        if (! array_key_exists($recordId, $cart)) {
            return;
        }

        $record = Record::query()->with('recordType')->find($recordId);

        if ($quantity < 1 || ! $record instanceof Record || ! $this->accepts($record)) {
            unset($cart[$recordId]);
        } else {
            $cart[$recordId] = $this->clamp($record, $quantity);
        }

        $this->store($cart);
    }

    public function remove(int $recordId): void
    {
        $cart = $this->stored();

        unset($cart[$recordId]);

        $this->store($cart);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array<int, array{record: Record, quantity: int, unitAmount: int, lineAmount: int}>
     */
    public function lines(): array
    {
        $cart = $this->stored();

        if ($cart === []) {
            return [];
        }

        $records = Record::query()->with('recordType')->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $lines = [];
        $kept = [];

        foreach ($cart as $recordId => $quantity) {
            $record = $records->get($recordId);

            if (! $record instanceof Record || ! $this->accepts($record)) {
                continue;
            }

            $kept[$recordId] = $this->clamp($record, $quantity);
            $unitAmount = $this->shop->toMinor((string) $this->shop->price($record));

            $lines[] = [
                'record' => $record,
                'quantity' => $kept[$recordId],
                'unitAmount' => $unitAmount,
                'lineAmount' => $unitAmount * $kept[$recordId],
            ];
        }

        $this->store($kept);

        return $lines;
    }

    public function quantityOf(Record $record): int
    {
        return $this->stored()[$record->id] ?? 0;
    }

    public function count(): int
    {
        return array_sum(array_column($this->lines(), 'quantity'));
    }

    public function subtotal(): int
    {
        return array_sum(array_column($this->lines(), 'lineAmount'));
    }

    public function maxQuantity(Record $record): int
    {
        return min(self::MAX_QUANTITY, $record->stock ?? self::MAX_QUANTITY);
    }

    public function accepts(Record $record): bool
    {
        return $this->shop->isPurchasable($record) && ! $this->shop->isSubscription($record);
    }

    private function clamp(Record $record, int $quantity): int
    {
        return max(1, min($quantity, $this->maxQuantity($record)));
    }

    /**
     * @return array<int, int>
     */
    private function stored(): array
    {
        $cart = session(self::SESSION_KEY, []);

        if (! is_array($cart)) {
            return [];
        }

        $clean = [];

        foreach ($cart as $recordId => $quantity) {
            if (is_int($recordId) && is_int($quantity) && $quantity > 0) {
                $clean[$recordId] = $quantity;
            }
        }

        return $clean;
    }

    /**
     * @param  array<int, int>  $cart
     */
    private function store(array $cart): void
    {
        session([self::SESSION_KEY => $cart]);
    }
}
