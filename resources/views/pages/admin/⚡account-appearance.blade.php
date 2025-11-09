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

<x-account-layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" label="{{ __('Theme') }}">
        <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
        <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
        <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
    </flux:radio.group>
</x-admin.settings-layout>
