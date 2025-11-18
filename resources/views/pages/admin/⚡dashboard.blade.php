<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public function render(): View
    {
        return $this->view()
            ->title(__('Dashboard'))
            ->layout('layouts::admin');
    }
};
?>

<div class="space-y-6 md:space-y-8">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="flex items-center mb-6 md:mb-0">
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        </div>
        <div class="flex flex-col md:flex-row items-center justify-end gap-4">
            <div class="w-50">
                <flux:date-picker mode="range" wire:model.live="datesFilter" presets="last7Days last30Days last3Months last6Months allTime" :max="now()->format('Y-m-d')" />
            </div>
        </div>
    </div>
</div>