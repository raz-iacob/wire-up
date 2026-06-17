@php
    $current = fn (string $name): bool => app('livewire')?->isLivewireRequest()
        ? str()->is(trim(str(route($name))->after(config('app.url'))->toString(), '/') ?: '/', app('livewire')->originalPath())
        : request()->routeIs($name);
@endphp

<div class="space-y-8">
    <flux:tabs variant="pills" scrollable>
        <flux:tab :href="route('admin.account-profile')" :selected="$current('admin.account-profile')" wire:navigate>{{ __('Profile') }}</flux:tab>
        <flux:tab :href="route('admin.account-password')" :selected="$current('admin.account-password')" wire:navigate>{{ __('Password') }}</flux:tab>
        <flux:tab :href="route('admin.account-appearance')" :selected="$current('admin.account-appearance')" wire:navigate>{{ __('Appearance') }}</flux:tab>
    </flux:tabs>

    <div class="w-full max-w-lg">
        {{ $slot }}
    </div>
</div>
