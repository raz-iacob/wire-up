<?php

declare(strict_types=1);

use App\Services\UpdateService;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    #[On('updates-checked')]
    public function refresh(): void
    {
        //
    }

    public function updateAvailable(): bool
    {
        return resolve(UpdateService::class)->updateAvailable();
    }
};
?>

<div>
    <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings-general')" :current="request()->routeIs('admin.settings-*')" :badge="$this->updateAvailable() ? '1' : null" wire:navigate.hover>{{ __('Settings') }}</flux:sidebar.item>
</div>
