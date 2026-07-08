<?php

declare(strict_types=1);

use App\Services\GoogleAnalyticsDataService;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    #[On('integrations-updated')]
    public function refresh(): void
    {
        //
    }

    public function configured(): bool
    {
        return resolve(GoogleAnalyticsDataService::class)->configured();
    }
};
?>

<div>
    @if ($this->configured())
        <flux:sidebar.item icon="chart-bar" :href="route('admin.analytics')" :current="request()->routeIs('admin.analytics')" wire:navigate.hover>{{ __('Analytics') }}</flux:sidebar.item>
    @endif
</div>
