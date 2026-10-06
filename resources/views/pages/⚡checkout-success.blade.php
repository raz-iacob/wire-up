<?php

declare(strict_types=1);

use App\Actions\CreateCheckoutAction;
use App\Actions\FulfillCheckoutAction;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

return new class extends Component
{
    #[Locked]
    public ?int $orderId = null;

    public function mount(FulfillCheckoutAction $fulfil, CartService $cart): void
    {
        $sessionId = request()->string('session_id')->value();

        if ($sessionId === '') {
            return;
        }

        try {
            $order = $fulfil->handle($sessionId);
        } catch (ApiErrorException $exception) {
            report($exception);

            $order = Order::query()->where('stripe_session_id', $sessionId)->first();
        }

        if (! $order instanceof Order || ! $this->belongsToVisitor($order)) {
            return;
        }

        $cart->clear();

        $this->orderId = $order->id;
    }

    #[Computed]
    public function order(): ?Order
    {
        return $this->orderId === null ? null : Order::query()->with('items')->find($this->orderId);
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Thank you'))
            ->layoutData(['description' => __('Your order has been received.')]);
    }

    private function belongsToVisitor(Order $order): bool
    {
        return session(CreateCheckoutAction::LAST_ORDER_KEY) === $order->id
            || (auth()->id() !== null && auth()->id() === $order->user_id);
    }
};
?>

@php
    $shop = resolve(\App\Services\ShopService::class);
    $order = $this->order;
@endphp

<section class="w-full">
    <div class="mx-auto max-w-2xl px-(--wire-gutter) py-16">
        <h1 class="text-(length:--wire-heading-size) tracking-tight">{{ __('Thank you') }}</h1>

        @if ($order === null)
            <p class="mt-6">{{ __('Your order has been received.') }}</p>
        @else
            <p class="mt-6">
                @if ($order->status === \App\Enums\OrderStatus::PROCESSING)
                    {{ __('Your payment is being processed. We will email you once it clears.') }}
                @elseif ($order->status === \App\Enums\OrderStatus::PENDING)
                    {{ __('We are confirming your payment with the bank. This page will show your order once it does.') }}
                @else
                    {{ __('Your order :reference is confirmed.', ['reference' => $order->reference]) }}
                @endif
            </p>

            <ul class="mt-8 divide-y divide-current/10 border-y border-current/10">
                @foreach ($order->items as $item)
                    <li wire:key="order-item-{{ $item->id }}" class="flex items-center justify-between gap-4 py-4">
                        <span>{{ $item->name }} <span class="opacity-70">× {{ $item->quantity }}</span></span>
                        <span class="tabular-nums">{{ $shop->formatMinor($item->line_amount, $order->currency) }}</span>
                    </li>
                @endforeach
            </ul>

            <dl class="mt-6 grid grid-cols-2 gap-y-2">
                @if ($order->shipping_amount > 0)
                    <dt>{{ __('Shipping') }}</dt>
                    <dd class="text-end tabular-nums">
                        {{ $shop->formatMinor($order->shipping_amount, $order->currency) }}
                    </dd>
                @endif
                @if ($order->tax_amount > 0)
                    <dt>{{ __('Tax') }}</dt>
                    <dd class="text-end tabular-nums">
                        {{ $shop->formatMinor($order->tax_amount, $order->currency) }}
                    </dd>
                @endif
                <dt class="font-semibold">{{ __('Total') }}</dt>
                <dd class="text-end font-semibold tabular-nums">
                    {{ $shop->formatMinor($order->total_amount, $order->currency) }}
                </dd>
            </dl>
        @endif

        <a
            href="{{ route('home') }}"
            wire:navigate
            class="mt-10 inline-block text-(--wire-accent) underline"
        >{{ __('Continue shopping') }}</a>
    </div>
</section>
