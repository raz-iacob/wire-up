<?php

declare(strict_types=1);

use App\Services\CartService;
use App\Services\ShopService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    #[Computed]
    public function visible(): bool
    {
        return resolve(ShopService::class)->isOpen();
    }

    #[Computed]
    public function count(): int
    {
        return resolve(CartService::class)->count();
    }

    #[On('cart-updated')]
    public function refreshCount(): void
    {
        unset($this->count);
    }
};
?>

<span>
    @if ($this->visible)
        <a
            href="{{ route('cart') }}"
            wire:navigate
            aria-label="{{ $this->count > 0 ? __('Cart (:count)', ['count' => $this->count]) : __('Cart') }}"
            title="{{ __('Cart') }}"
            class="relative inline-flex items-center justify-center rounded-(--wire-btn-radius) p-1.5 text-current transition hover:opacity-70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current"
        >
            <flux:icon.shopping-cart variant="mini" class="size-5" />
            @if ($this->count > 0)
                <span class="absolute -end-1 -top-1 flex min-w-4 items-center justify-center rounded-full bg-(--wire-primary-bg) px-1 text-[0.65rem] leading-4 font-bold text-(--wire-primary-text) tabular-nums">{{ $this->count }}</span>
            @endif
        </a>
    @endif
</span>
