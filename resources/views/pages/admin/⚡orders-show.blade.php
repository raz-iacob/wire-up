<?php

declare(strict_types=1);

use App\Actions\MarkOrderFulfilledAction;
use App\Models\Order;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load('items', 'user');
    }

    public function markFulfilled(MarkOrderFulfilledAction $action): void
    {
        $this->authorize('orders.edit');

        if ($action->handle($this->order)) {
            Flux::toast(__('Order marked as fulfilled.'), variant: 'success');
        }
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Order :reference', ['reference' => $this->order->reference]))
            ->layout('layouts::admin');
    }
};
?>

@php
    $shop = resolve(\App\Services\ShopService::class);
@endphp

<div class="grid items-start gap-10 md:grid-cols-5">
    <div class="space-y-8 md:col-span-3">
        @if ($order->oversold)
            <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Oversold')">
                <flux:callout.text>
                    {{ __('This payment arrived after the checkout had expired and there was not enough stock left. Check your stock before you ship it.') }}</flux:callout.text>
            </flux:callout>
        @endif

        <flux:fieldset>
            <div class="flex items-center justify-between gap-3">
                <flux:legend class="mb-0!">{{ __('Order :reference', ['reference' => $order->reference]) }}</flux:legend>
                <flux:badge size="sm" :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
            </div>
            <flux:description>{{ __('Placed :date', ['date' => $order->created_at->format('M d, Y H:i')]) }}</flux:description>

            <flux:table class="mt-6">
                <flux:table.columns>
                    <flux:table.column>{{ __('Item') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($order->items as $item)
                        <flux:table.row wire:key="item-{{ $item->id }}">
                            <flux:table.cell>
                                {{ $item->name }}
                                @if ($item->sku)
                                    <flux:text size="sm" variant="subtle">{{ $item->sku }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $item->quantity }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                {{ $shop->formatMinor($item->line_amount, $order->currency) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <dl class="mt-6 grid grid-cols-2 gap-y-2 text-sm">
                <dt>{{ __('Subtotal') }}</dt>
                <dd class="text-end tabular-nums">
                    {{ $shop->formatMinor($order->subtotal_amount, $order->currency) }}
                </dd>
                @if ($order->shipping_amount > 0)
                    <dt>{{ __('Shipping') }}{{ $order->shipping_method ? ' · '.$order->shipping_method : '' }}</dt>
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
                @if ($order->discount_amount > 0)
                    <dt>{{ __('Discount') }}</dt>
                    <dd class="text-end tabular-nums">
                        −{{ $shop->formatMinor($order->discount_amount, $order->currency) }}
                    </dd>
                @endif
                <dt class="font-semibold">{{ __('Total') }}</dt>
                <dd class="text-end font-semibold tabular-nums">
                    {{ $shop->formatMinor($order->total_amount, $order->currency) }}
                </dd>
                @if ($order->refunded_amount > 0)
                    <dt>{{ __('Refunded') }}</dt>
                    <dd class="text-end tabular-nums">
                        −{{ $shop->formatMinor($order->refunded_amount, $order->currency) }}
                    </dd>
                @endif
            </dl>
        </flux:fieldset>
    </div>

    <div class="md:col-span-2">
        <flux:card class="flex flex-col gap-6 md:sticky md:top-24">
            <div class="space-y-1">
                <flux:heading>{{ __('Customer') }}</flux:heading>
                <flux:text>{{ $order->name ?: __('Guest') }}</flux:text>
                @if ($order->email)
                    <flux:link :href="'mailto:'.$order->email">{{ $order->email }}</flux:link>
                @endif
                @if ($order->user)
                    <flux:text size="sm" variant="subtle">{{ __('Signed in as a member') }}</flux:text>
                @endif
            </div>

            @if (is_array($order->shipping_address))
                <div class="space-y-1">
                    <flux:heading>{{ __('Ship to') }}</flux:heading>
                    <flux:text>{{ data_get($order->shipping_address, 'name') }}</flux:text>
                    @php
                        $address = (array) data_get($order->shipping_address, 'address', []);
                        $addressLines = array_filter([
                            $address['line1'] ?? null,
                            $address['line2'] ?? null,
                            mb_trim(implode(' ', array_filter([$address['city'] ?? null, $address['state'] ?? null, $address['postal_code'] ?? null]))),
                            $address['country'] ?? null,
                        ]);
                    @endphp
                    @foreach ($addressLines as $line)
                        <flux:text>{{ $line }}</flux:text>
                    @endforeach
                </div>
            @endif

            @if ($order->status === \App\Enums\OrderStatus::PAID)
                @can('orders.edit')
                    <flux:button variant="primary" icon="truck" wire:click="markFulfilled" class="w-full">
                        {{ __('Mark as fulfilled') }}</flux:button>
                @endcan
            @elseif ($order->fulfilled_at)
                <flux:text size="sm">{{ __('Fulfilled :date', ['date' => $order->fulfilled_at->format('M d, Y H:i')]) }}</flux:text>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <flux:button
                    :href="route('admin.orders-index')"
                    wire:navigate
                    icon="arrow-left"
                >{{ __('Back') }}</flux:button>
                @if ($order->stripeDashboardUrl())
                    <flux:button
                        :href="$order->stripeDashboardUrl()"
                        target="_blank"
                        icon="arrow-top-right-on-square"
                    >{{ __('Stripe') }}</flux:button>
                @endif
            </div>
        </flux:card>
    </div>
</div>

@section('header-content')
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('admin.orders-index')" wire:navigate class="pl-3 md:pl-0">
            {{ __('Orders') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $order->reference }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
