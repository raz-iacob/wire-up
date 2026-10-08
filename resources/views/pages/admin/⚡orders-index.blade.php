<?php

declare(strict_types=1);

use App\Actions\CancelOrderAction;
use App\Actions\MarkOrderFulfilledAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Stripe\Exception\ApiErrorException;

return new class extends Component
{
    use WithPagination;

    private const array SORTABLE = ['created_at', 'total_amount', 'reference'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $field && $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->sortBy = $field;
    }

    public function markFulfilled(int $id, MarkOrderFulfilledAction $action): void
    {
        $this->authorize('orders.edit');

        $order = Order::query()->find($id);

        if ($order instanceof Order && $action->handle($order)) {
            Flux::toast(__('Order marked as fulfilled.'), variant: 'success');
        }
    }

    public function cancel(int $id, CancelOrderAction $action): void
    {
        $this->authorize('orders.edit');

        $order = Order::query()->find($id);

        if (! $order instanceof Order) {
            return;
        }

        try {
            $cancelled = $action->handle($order);
        } catch (ApiErrorException $exception) {
            report($exception);

            Flux::toast(__('Stripe could not be reached. Try again in a moment.'), variant: 'danger');

            return;
        }

        Flux::toast($cancelled ? __('Order cancelled and its stock released.') : __('This order was paid in the meantime, so it was kept.'), variant: $cancelled ? 'success' : 'warning');
    }

    /** @return LengthAwarePaginator<int, Order> */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        $status = OrderStatus::tryFrom($this->status);
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'created_at';

        return Order::query()
            ->when($status, fn (Builder $query, OrderStatus $status): Builder => $query->where('status', $status))
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->whereAny(['reference', 'email', 'name'], 'like', "%{$search}%"))
            ->orderBy($sortBy, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate(20);
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Orders'))
            ->layout('layouts::admin');
    }
};
?>

@php
    $shop = resolve(\App\Services\ShopService::class);
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
        <flux:dropdown position="bottom" align="start">
            <flux:button class="shrink-0" size="sm" icon="funnel" iconVariant="outline">{{ __('Filter') }}</flux:button>

            <flux:menu>
                <flux:menu.radio.group wire:model.live="status" heading="{{ __('Status') }}">
                    <flux:menu.radio value="" checked>{{ __('All') }}</flux:menu.radio>
                    @foreach (\App\Enums\OrderStatus::cases() as $statusOption)
                        <flux:menu.radio value="{{ $statusOption->value }}">
                            {{ $statusOption->label() }}</flux:menu.radio>
                    @endforeach
                </flux:menu.radio.group>
                @if ($status !== '')
                    <flux:menu.separator />
                    <flux:menu.item icon="x-mark" wire:click="$set('status', '')">
                        {{ __('Clear filters') }}</flux:menu.item>
                @endif
            </flux:menu>
        </flux:dropdown>
        @if (\App\Enums\OrderStatus::tryFrom($status))
            <x-admin.filter-chip
                :label="\App\Enums\OrderStatus::from($status)->label()"
                wire:click="$set('status', '')"
            />
        @endif

        <div class="ms-auto min-w-40 flex-1 sm:w-52 sm:flex-none">
            <flux:input
                icon="magnifying-glass"
                wire:model.live="search"
                size="sm"
                placeholder="{{ __('Search...') }}"
                clearable
            />
        </div>
    </div>

    <flux:table :paginate="$this->orders" class="md:w-full md:table-fixed">
        <flux:table.columns>
            <flux:table.column
                class="w-32"
                sortable
                :sorted="$sortBy === 'reference'"
                :direction="$sortDirection"
                wire:click="sort('reference')"
            >
                {{ __('Order') }}</flux:table.column>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column class="w-40">{{ __('Status') }}</flux:table.column>
            <flux:table.column
                class="w-32"
                align="end"
                sortable
                :sorted="$sortBy === 'total_amount'"
                :direction="$sortDirection"
                wire:click="sort('total_amount')"
            >
                {{ __('Total') }}</flux:table.column>
            <flux:table.column
                class="w-40"
                sortable
                :sorted="$sortBy === 'created_at'"
                :direction="$sortDirection"
                wire:click="sort('created_at')"
            >
                {{ __('Placed on') }}</flux:table.column>
            <flux:table.column class="w-10"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->orders as $order)
                <flux:table.row wire:key="order-{{ $order->id }}">
                    <flux:table.cell>
                        <a
                            href="{{ route('admin.orders-show', $order) }}"
                            wire:navigate
                            class="font-medium hover:underline"
                        >{{ $order->reference }}</a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="min-w-0">
                            <flux:text variant="strong" class="truncate">{{ $order->name ?: __('Guest') }}</flux:text>
                            @if ($order->email)
                                <flux:text class="truncate">{{ $order->email }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge
                            size="sm"
                            :color="$order->status->color()"
                        >{{ $order->status->label() }}</flux:badge>
                        @if ($order->oversold)
                            <flux:badge
                                size="sm"
                                color="red"
                                icon="exclamation-triangle"
                            >{{ __('Oversold') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">
                        {{ $shop->formatMinor($order->total_amount, $order->currency) }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $order->created_at->format('M d, Y H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown class="flex justify-end">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="ellipsis-horizontal"
                                square
                                :aria-label="__('Actions')"
                            />
                            <flux:menu>
                                <flux:menu.item
                                    icon="eye"
                                    href="{{ route('admin.orders-show', $order) }}"
                                    wire:navigate
                                >
                                    {{ __('View') }}
                                </flux:menu.item>
                                @can('orders.edit')
                                    @if ($order->status === \App\Enums\OrderStatus::PAID)
                                        <flux:menu.item icon="truck" wire:click="markFulfilled({{ $order->id }})">
                                            {{ __('Mark as fulfilled') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($order->status->canBeRefunded())
                                        <flux:menu.item
                                            icon="receipt-refund"
                                            href="{{ route('admin.orders-show', ['order' => $order, 'refund' => 1]) }}"
                                            wire:navigate
                                        >
                                            {{ __('Refund…') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($order->status === \App\Enums\OrderStatus::PENDING)
                                        <flux:menu.separator />
                                        <flux:menu.item
                                            icon="x-circle"
                                            variant="danger"
                                            wire:click="cancel({{ $order->id }})"
                                        >
                                            {{ __('Cancel') }}
                                        </flux:menu.item>
                                    @endif
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">
                        {{ __('No orders yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>

@section('header-content')
    <flux:breadcrumbs>
        <flux:breadcrumbs.item class="pl-3 md:pl-0">{{ __('Orders') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
