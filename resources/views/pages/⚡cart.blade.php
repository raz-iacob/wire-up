<?php

declare(strict_types=1);

use App\Actions\CancelOrderAction;
use App\Actions\CreateCheckoutAction;
use App\Models\Order;
use App\Models\Record;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

return new class extends Component
{
    public function mount(CancelOrderAction $cancel): void
    {
        $reference = request()->string('cancelled')->value();
        $order = $reference === '' ? null : Order::query()->where('reference', $reference)->first();

        if (! $order instanceof Order || session(CreateCheckoutAction::LAST_ORDER_KEY) !== $order->id) {
            return;
        }

        try {
            $cancel->handle($order);
        } catch (ApiErrorException $exception) {
            report($exception);
        }
    }

    /**
     * @return array<int, array{record: Record, quantity: int, unitAmount: int, lineAmount: int}>
     */
    #[Computed]
    public function lines(): array
    {
        return resolve(CartService::class)->lines();
    }

    public function changeQuantity(int $recordId, int $quantity, CartService $cart): void
    {
        $cart->update($recordId, $quantity);

        $this->refreshCart();
    }

    public function remove(int $recordId, CartService $cart): void
    {
        $cart->remove($recordId);

        $this->refreshCart();
    }

    public function checkout(CreateCheckoutAction $checkout): void
    {
        $user = auth()->user();

        try {
            $url = $checkout->handle($user instanceof User ? $user : null);
        } catch (ApiErrorException $exception) {
            report($exception);

            $this->addError('checkout', __('Checkout is not available right now. Please try again later.'));

            return;
        }

        $this->redirect($url);
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Cart'))
            ->layoutData(['description' => __('Review the items in your cart.')]);
    }

    private function refreshCart(): void
    {
        unset($this->lines);

        $this->dispatch('cart-updated');
    }
};
?>

@php
    $shop = resolve(\App\Services\ShopService::class);
@endphp

<section class="w-full">
    <div class="mx-auto max-w-3xl px-(--wire-gutter) py-16">
        <h1 class="text-(length:--wire-heading-size) tracking-tight">{{ __('Cart') }}</h1>

        @if ($this->lines === [])
            <div class="mt-8 flex flex-col items-start gap-4">
                <p>{{ __('Your cart is empty.') }}</p>
                <a
                    href="{{ route('home') }}"
                    wire:navigate
                    class="text-(--wire-accent) underline"
                >{{ __('Continue shopping') }}</a>
            </div>
        @else
            <ul class="mt-8 divide-y divide-current/10 border-y border-current/10">
                @foreach ($this->lines as $line)
                    <li wire:key="cart-line-{{ $line['record']->id }}" class="flex flex-wrap items-center gap-4 py-5">
                        <div class="min-w-0 flex-1">
                            @if ($line['record']->recordType->has_detail_page)
                                <a
                                    href="{{ $line['record']->getUrl() }}"
                                    wire:navigate
                                    class="font-medium hover:underline"
                                >{{ $line['record']->displayHeading() }}</a>
                            @else
                                <span class="font-medium">{{ $line['record']->displayHeading() }}</span>
                            @endif
                            <p class="text-sm opacity-70">{{ $shop->formatMinor($line['unitAmount']) }}</p>
                        </div>

                        <label
                            class="sr-only"
                            for="cart-quantity-{{ $line['record']->id }}"
                        >{{ __('Quantity') }}</label>
                        <input
                            id="cart-quantity-{{ $line['record']->id }}"
                            type="number"
                            min="0"
                            max="{{ resolve(CartService::class)->maxQuantity($line['record']) }}"
                            step="1"
                            value="{{ $line['quantity'] }}"
                            wire:change="changeQuantity({{ $line['record']->id }}, $event.target.valueAsNumber || 0)"
                            class="wire-field w-20 rounded-(--wire-btn-radius) bg-(--wire-input-bg) px-3 py-2 text-base text-(--wire-input-text) focus:outline-none"
                        />

                        <span class="w-24 text-end font-medium tabular-nums">{{ $shop->formatMinor($line['lineAmount']) }}</span>

                        <button
                            type="button"
                            wire:click="remove({{ $line['record']->id }})"
                            aria-label="{{ __('Remove :item', ['item' => $line['record']->displayHeading()]) }}"
                            class="rounded-(--wire-btn-radius) p-1.5 opacity-70 transition hover:opacity-100 focus-visible:outline-2 focus-visible:outline-current"
                        >
                            <flux:icon.x-mark variant="mini" class="size-5" />
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6 flex items-center justify-between text-lg">
                <span>{{ __('Subtotal') }}</span>
                <span class="font-semibold tabular-nums">{{ $shop->formatMinor(array_sum(array_column($this->lines, 'lineAmount'))) }}</span>
            </div>
            <p class="mt-1 text-sm opacity-70">{{ __('Shipping and taxes are worked out at checkout.') }}</p>

            @error('checkout')
                <div class="mt-6 rounded-(--wire-radius) bg-red-100 px-3 py-2 text-sm font-medium text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div class="mt-8 flex justify-end">
                <button
                    type="button"
                    wire:click="checkout"
                    wire:loading.attr="disabled"
                    wire:target="checkout"
                    class="wire-btn inline-flex items-center justify-center rounded-(--wire-btn-radius) bg-(--wire-primary-bg) px-6 py-3 text-base font-medium text-(--wire-primary-text) transition [--wire-btn-border:var(--wire-primary-border)] hover:opacity-90 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="checkout">{{ __('Checkout') }}</span>
                    <span wire:loading wire:target="checkout">{{ __('Opening checkout…') }}</span>
                </button>
            </div>
        @endif
    </div>
</section>
