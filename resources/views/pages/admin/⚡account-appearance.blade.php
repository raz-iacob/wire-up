<?php

declare(strict_types=1);

use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::admin')]
class extends Component {};
?>

<x-account-layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" label="{{ __('Theme') }}">
        <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
        <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
        <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
    </flux:radio.group>
</x-admin.settings-layout>
