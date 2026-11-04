@php
    $section = resolve(\App\Services\SettingsNavigation::class)->currentSection();
@endphp

@if ($section !== null && count($section['tabs']) > 1)
    <flux:tabs variant="pills" scrollable class="mb-8">
        @foreach ($section['tabs'] as $tab)
            <flux:tab :href="route($tab['route'])" :selected="$tab['current']" wire:navigate>
                {{ $tab['label'] }}
                @if ($tab['badge'] !== null)
                    <flux:badge size="sm" class="ms-1.5">{{ $tab['badge'] }}</flux:badge>
                @endif
            </flux:tab>
        @endforeach
    </flux:tabs>
@endif
