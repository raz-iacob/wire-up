
<div class="space-y-6 md:space-y-8">
    <div class="hidden md:block">
        <flux:heading size="xl" level="1">{{ __('Account') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your profile and account settings') }}</flux:subheading>
        <flux:separator variant="subtle" class="hidden md:block" />
    </div>

    <div class="flex items-start max-md:flex-col">
        <div class="hidden md:block me-10 w-full pb-4 md:w-[220px]">
            <flux:navlist>
                <flux:navlist.item :href="route('admin.account-profile')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
                <flux:navlist.item :href="route('admin.account-password')" wire:navigate>{{ __('Password') }}</flux:navlist.item>
                <flux:navlist.item :href="route('admin.account-appearance')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
            </flux:navlist>
        </div>

        <div class="md:hidden me-10 w-full pb-4 md:w-[220px]">
            <flux:navbar class="pt-0">
                <flux:navbar.item :href="route('admin.account-profile')" wire:navigate>{{ __('Profile') }}</flux:navbar.item>
                <flux:navbar.item :href="route('admin.account-password')" wire:navigate>{{ __('Password') }}</flux:navbar.item>
                <flux:navbar.item :href="route('admin.account-appearance')" wire:navigate>{{ __('Appearance') }}</flux:navbar.item>
            </flux:navbar>
        </div>

        <div class="flex-1 self-stretch max-md:pt-6">
            <flux:heading>{{ $heading ?? '' }}</flux:heading>
            <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

            <div class="mt-5 w-full max-w-lg">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>