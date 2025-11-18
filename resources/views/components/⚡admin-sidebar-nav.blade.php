<?php

declare(strict_types=1);

use Livewire\Component;

return new class extends Component
{
    //
};
?>

<flux:sidebar.nav>
    <flux:sidebar.item icon="squares-2x2" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate.hover>{{ __('Dashboard') }}</flux:sidebar.item>
    <flux:sidebar.item icon="cursor-arrow-ripple" :href="route('admin.pages-index')" :current="request()->routeIs('admin.pages-index')" wire:navigate.hover>{{ __('Pages') }}</flux:sidebar.item>
    <flux:sidebar.item icon="users" :href="route('admin.users-index')" :current="request()->routeIs('admin.users-index')" wire:navigate.hover>{{ __('Users') }}</flux:sidebar.item>
</flux:sidebar.nav>