<?php

declare(strict_types=1);

use App\Services\SettingsNavigation;
use App\Services\UpdateService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    #[On('updates-checked')]
    public function refresh(): void
    {
        unset($this->updateAvailable, $this->sections);
    }

    #[Computed]
    public function updateAvailable(): bool
    {
        return resolve(UpdateService::class)->updateAvailable();
    }

    /**
     * @return list<array{key: string, label: string, route: string, current: bool, badge: ?string, tabs: list<array{label: string, route: string, current: bool, badge: ?string}>}>
     */
    #[Computed]
    public function sections(): array
    {
        return resolve(SettingsNavigation::class)->sections();
    }
};
?>

<div>
    <flux:sidebar.group expandable :heading="__('Settings')" :expanded="false" class="grid" data-wireup-settings-group>
        <x-slot:icon>
            <div class="relative">
                <flux:icon icon="cog-6-tooth" variant="outline" class="size-4" />
                @if ($this->updateAvailable)
                    <div class="absolute -inset-e-0.5 -top-0.5 size-2 animate-pulse rounded-full bg-red-500"></div>
                @endif
            </div>
        </x-slot:icon>
        @foreach ($this->sections as $section)
            <flux:sidebar.item
                :href="route($section['route'])"
                :current="$section['current']"
                :badge="$section['badge']"
                wire:navigate.hover
                wire:key="settings-{{ $section['key'] }}"
            >
                {{ $section['label'] }}</flux:sidebar.item>
        @endforeach
    </flux:sidebar.group>
</div>
