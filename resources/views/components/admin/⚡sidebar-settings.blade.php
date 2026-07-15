<?php

declare(strict_types=1);

use App\Services\UpdateService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    #[On('updates-checked')]
    public function refresh(): void
    {
        unset($this->updateAvailable);
    }

    #[Computed]
    public function updateAvailable(): bool
    {
        return resolve(UpdateService::class)->updateAvailable();
    }
};
?>

<div>
    <flux:sidebar.group expandable :heading="__('Settings')" :expanded="false" class="grid">
        <x-slot:icon>
            <div class="relative">
                <flux:icon icon="cog-6-tooth" variant="outline" class="size-4" />
                @if ($this->updateAvailable)
                    <div class="absolute -inset-e-0.5 -top-0.5 size-2 rounded-full bg-red-500 animate-pulse"></div>
                @endif
            </div>
        </x-slot:icon>
        <flux:sidebar.item :href="route('admin.settings-general')" :current="request()->routeIs('admin.settings-general')" wire:navigate.hover>{{ __('General') }}</flux:sidebar.item>
        <flux:sidebar.item :href="route('admin.settings-identity')" :current="request()->routeIs('admin.settings-identity')" wire:navigate.hover>{{ __('Identity') }}</flux:sidebar.item>
        <flux:sidebar.item :href="route('admin.settings-design')" :current="request()->routeIs('admin.settings-design')" wire:navigate.hover>{{ __('Design') }}</flux:sidebar.item>
        <flux:sidebar.item :href="route('admin.settings-menus')" :current="request()->routeIs('admin.settings-menus')" wire:navigate.hover>{{ __('Menus') }}</flux:sidebar.item>
        <flux:sidebar.item :href="route('admin.settings-content-types')" :current="request()->routeIs('admin.settings-content-types')" wire:navigate.hover>{{ __('Content Types') }}</flux:sidebar.item>
        <flux:sidebar.item :href="route('admin.settings-social')" :current="request()->routeIs('admin.settings-social')" wire:navigate.hover>{{ __('Social') }}</flux:sidebar.item>
        <flux:sidebar.item :href="route('admin.settings-integrations')" :current="request()->routeIs('admin.settings-integrations')" wire:navigate.hover>{{ __('App Integrations') }}</flux:sidebar.item>
        @can('roles.view')
            <flux:sidebar.item :href="route('admin.settings-roles')" :current="request()->routeIs('admin.settings-roles')" wire:navigate.hover>{{ __('User Roles') }}</flux:sidebar.item>
        @endcan
        <flux:sidebar.item :href="route('admin.settings-updates')" :current="request()->routeIs('admin.settings-updates')" :badge="$this->updateAvailable ? '1' : null" wire:navigate.hover>{{ __('Updates') }}</flux:sidebar.item>
    </flux:sidebar.group>
</div>
