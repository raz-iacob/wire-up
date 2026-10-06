<?php

declare(strict_types=1);

use App\Models\Record;
use App\Services\CartService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public Record $record;

    public int $quantity = 1;

    #[Computed]
    public function maxQuantity(): int
    {
        return resolve(CartService::class)->maxQuantity($this->record);
    }

    public function add(CartService $cart): void
    {
        if (! $cart->accepts($this->record)) {
            $this->addError('quantity', __('This item is no longer available.'));

            return;
        }

        $this->validate(
            ['quantity' => ['required', 'integer', 'min:1', 'max:'.$this->maxQuantity()]],
            ['quantity.max' => __('Only :count left in stock.', ['count' => $this->maxQuantity()])],
            ['quantity' => __('quantity')],
        );

        $cart->add($this->record, $this->quantity);

        $this->quantity = 1;
        $this->dispatch('cart-updated');

        Flux::toast(__('Added to your cart.'), variant: 'success');
    }
};
?>

<form wire:submit="add" class="mt-2 flex flex-col gap-2">
    <div class="flex flex-wrap items-center gap-3">
        <label class="sr-only" for="buy-quantity-{{ $record->id }}">{{ __('Quantity') }}</label>
        <input
            id="buy-quantity-{{ $record->id }}"
            type="number"
            min="1"
            max="{{ $this->maxQuantity }}"
            step="1"
            wire:model="quantity"
            class="wire-field w-20 rounded-(--wire-btn-radius) bg-(--wire-input-bg) px-3 py-3 text-base text-(--wire-input-text) focus:outline-none"
        />
        <button
            type="submit"
            class="wire-btn inline-flex items-center justify-center gap-2 rounded-(--wire-btn-radius) bg-(--wire-primary-bg) px-6 py-3 text-base font-medium text-(--wire-primary-text) transition [--wire-btn-border:var(--wire-primary-border)] hover:opacity-90 disabled:opacity-50"
            wire:loading.attr="disabled"
            wire:target="add"
        >
            <flux:icon.shopping-cart variant="mini" class="size-5" />
            {{ __('Add to cart') }}
        </button>
    </div>

    @error('quantity')
        <p class="text-sm font-medium text-red-600">{{ $message }}</p>
    @enderror
</form>
