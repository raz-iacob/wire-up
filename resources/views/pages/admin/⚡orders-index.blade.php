<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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
    <div class="flex items-center gap-3">
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
            </flux:menu>
        </flux:dropdown>

        <div class="w-full sm:shrink-0 md:w-52">
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
                    <flux:table.cell class="truncate">{{ $order->name ?: ($order->email ?: '—') }}</flux:table.cell>
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
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">
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
