<?php

declare(strict_types=1);

use Livewire\Component;

return new class extends Component
{
    public function startsWith(string $path): bool
    {
        return str(request()->url())->startsWith(config('app.url').'/admin/'.$path);
    }
};

?>

<flux:sidebar.nav>
    <flux:sidebar.item icon="squares-2x2" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate.hover>{{ __('Dashboard') }}</flux:sidebar.item>
    <flux:sidebar.item icon="cursor-arrow-ripple" :href="route('admin.pages-index')" :current="$this->startsWith('pages')" wire:navigate.hover>{{ __('Pages') }}</flux:sidebar.item>
    <flux:sidebar.item icon="users" :href="route('admin.users-index')" :current="$this->startsWith('users')" wire:navigate.hover>{{ __('Users') }}</flux:sidebar.item>
</flux:sidebar.nav>