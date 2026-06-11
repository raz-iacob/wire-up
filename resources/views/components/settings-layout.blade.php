<div class="space-y-6">
    <div class="hidden md:block">
        <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Manage your site configuration') }}</flux:subheading>
    </div>

    <flux:navbar>
        <flux:navbar.item :href="route('admin.settings-identity')" wire:navigate>{{ __('Identity') }}</flux:navbar.item>
    </flux:navbar>

    <div class="w-full max-w-5xl">
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-10">
            {{ $slot }}
        </div>
    </div>
</div>
