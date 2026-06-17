<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public function render(): View
    {
        return $this->view()
            ->title(__('Appearance'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.account-layout>
    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" label="{{ __('Theme') }}">
        <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
        <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
        <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
    </flux:radio.group>
</x-admin.account-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.account-profile') }}" wire:navigate>
            {{ __('Account') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ __('Appearance') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Appearance') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.account-profile') }}" wire:navigate>{{ __('Account') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
