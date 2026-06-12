<div class="space-y-6">
    <div class="hidden md:block">
        <flux:heading size="xl" level="1">{{ __('Account') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Manage your profile and account settings') }}</flux:subheading>
    </div>

    <flux:navbar class="pt-0">
        <flux:navbar.item :href="route('admin.account-profile')" wire:navigate>{{ __('Profile') }}</flux:navbar.item>
        <flux:navbar.item :href="route('admin.account-password')" wire:navigate>{{ __('Password') }}</flux:navbar.item>
        <flux:navbar.item :href="route('admin.account-appearance')" wire:navigate>{{ __('Appearance') }}</flux:navbar.item>
    </flux:navbar>

    <div class="w-full max-w-lg">
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-10">
            {{ $slot }}
        </div>
    </div>
</div>
