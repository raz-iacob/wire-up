<?php

declare(strict_types=1);

use App\Actions\StartSubscriptionCheckoutAction;
use App\Models\Record;
use App\Models\User;
use App\Services\CartService;
use App\Services\ShopService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

return new class extends Component
{
    public Record $record;

    public int $quantity = 1;

    #[Computed]
    public function billing(): string
    {
        return resolve(ShopService::class)->billing($this->record);
    }

    #[Computed]
    public function inCart(): int
    {
        return resolve(CartService::class)->quantityOf($this->record);
    }

    #[On('cart-updated')]
    public function refreshCart(): void
    {
        unset($this->inCart);
    }

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

    public function subscribe(StartSubscriptionCheckoutAction $action): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            session()->put('url.intended', $this->record->getUrl());

            $this->redirectRoute('login');

            return;
        }

        try {
            $url = $action->handle($user, $this->record);
        } catch (ApiErrorException $exception) {
            report($exception);

            $this->addError('subscribe', __('Subscribing is not available right now. Please try again later.'));

            return;
        }

        $this->redirect($url);
    }
};
?>

<div>
    @if ($this->billing !== \App\Services\ShopService::ONE_TIME)
        <div class="mt-2 flex flex-col gap-2">
            <div>
                <button
                    type="button"
                    wire:click="subscribe"
                    wire:loading.attr="disabled"
                    wire:target="subscribe"
                    class="wire-btn inline-flex items-center justify-center gap-2 rounded-(--wire-btn-radius) bg-(--wire-primary-bg) px-6 py-3 text-base font-medium text-(--wire-primary-text) transition [--wire-btn-border:var(--wire-primary-border)] hover:opacity-90 disabled:opacity-50"
                >
                    <flux:icon.arrow-path variant="mini" class="size-5" />
                    {{ __('Subscribe') }}
                </button>
            </div>
            <p class="text-sm opacity-70">
                {{ $this->billing === \App\Services\ShopService::MONTHLY ? __('Billed every month until you cancel.') : __('Billed every year until you cancel.') }}
            </p>

            @error('subscribe')
                <p class="text-sm font-medium text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @else
        <form wire:submit="add" class="mt-2 flex flex-col gap-3">
            <div class="flex flex-wrap items-stretch gap-3">
                <button
                    type="submit"
                    class="wire-btn inline-flex min-w-48 items-center justify-center gap-2 rounded-(--wire-btn-radius) bg-(--wire-primary-bg) px-8 py-4 text-lg font-semibold text-(--wire-primary-text) transition [--wire-btn-border:var(--wire-primary-border)] hover:opacity-90 disabled:opacity-50"
                    wire:loading.attr="disabled"
                    wire:target="add"
                >
                    {{ __('Add to cart') }}
                </button>

                <div
                    x-data="{ max: {{ $this->maxQuantity }} }"
                    class="wire-field inline-flex items-center rounded-(--wire-btn-radius) bg-(--wire-input-bg) text-(--wire-input-text)"
                >
                    <button
                        type="button"
                        x-on:click="$wire.quantity = Math.max(1, Number($wire.quantity) - 1)"
                        x-bind:disabled="Number($wire.quantity) <= 1"
                        aria-label="{{ __('Fewer') }}"
                        class="flex h-full items-center px-4 opacity-70 transition hover:opacity-100 disabled:opacity-30"
                    >
                        <flux:icon.minus variant="mini" class="size-5" />
                    </button>
                    <label class="sr-only" for="buy-quantity-{{ $record->id }}">{{ __('Quantity') }}</label>
                    <input
                        id="buy-quantity-{{ $record->id }}"
                        type="number"
                        min="1"
                        max="{{ $this->maxQuantity }}"
                        step="1"
                        wire:model="quantity"
                        class="[&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none w-12 [appearance:textfield] bg-transparent text-center text-lg tabular-nums focus:outline-none"
                    />
                    <button
                        type="button"
                        x-on:click="$wire.quantity = Math.min(max, Number($wire.quantity) + 1)"
                        x-bind:disabled="Number($wire.quantity) >= max"
                        aria-label="{{ __('More') }}"
                        class="flex h-full items-center px-4 opacity-70 transition hover:opacity-100 disabled:opacity-30"
                    >
                        <flux:icon.plus variant="mini" class="size-5" />
                    </button>
                </div>
            </div>

            @if ($this->inCart > 0)
                <p class="text-sm">
                    {{ __(':count in your cart', ['count' => $this->inCart]) }} ·
                    <a
                        href="{{ route('cart') }}"
                        wire:navigate
                        class="text-(--wire-accent) underline"
                    >{{ __('View cart') }}</a>
                </p>
            @endif

            @error('quantity')
                <p class="text-sm font-medium text-red-600">{{ $message }}</p>
            @enderror
        </form>
    @endif
</div>
