@php
    $current = fn (string $name): bool => app('livewire')?->isLivewireRequest()
        ? str()->is(trim(str(route($name))->after(config('app.url'))->toString(), '/') ?: '/', app('livewire')->originalPath())
        : request()->routeIs($name);

    $contentTypesActive = app('livewire')?->isLivewireRequest()
        ? str(app('livewire')->originalPath())->startsWith('admin/settings/content-types')
        : request()->routeIs('admin.record-types-*');
@endphp

<div class="space-y-8">
    <flux:tabs variant="pills" scrollable>
        <flux:tab :href="route('admin.settings-general')" :selected="$current('admin.settings-general')" wire:navigate>{{ __('General') }}</flux:tab>
        <flux:tab :href="route('admin.settings-identity')" :selected="$current('admin.settings-identity')" wire:navigate>{{ __('Identity') }}</flux:tab>
        <flux:tab :href="route('admin.settings-design')" :selected="$current('admin.settings-design')" wire:navigate>{{ __('Design') }}</flux:tab>
        <flux:tab :href="route('admin.settings-menus')" :selected="$current('admin.settings-menus')" wire:navigate>{{ __('Menus') }}</flux:tab>
        <flux:tab :href="route('admin.record-types-index')" :selected="$contentTypesActive" wire:navigate>{{ __('Content Types') }}</flux:tab>
        <flux:tab :href="route('admin.settings-social')" :selected="$current('admin.settings-social')" wire:navigate>{{ __('Social') }}</flux:tab>
        <flux:tab :href="route('admin.settings-integrations')" :selected="$current('admin.settings-integrations')" wire:navigate>{{ __('Integrations') }}</flux:tab>
    </flux:tabs>

    <div class="w-full max-w-5xl">
        {{ $slot }}
    </div>
</div>
