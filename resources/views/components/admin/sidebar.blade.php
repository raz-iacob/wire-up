<flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
    <flux:sidebar.header>
        <flux:sidebar.brand
            href="/"
            logo="{{ Vite::asset('resources/images/logo-icon-light.svg') }}"
            logo:dark="{{ Vite::asset('resources/images/logo-icon-dark.svg') }}"
            name="{{ config('app.name') }}"
        />
        <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.item icon="squares-2x2" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate.hover>{{ __('Dashboard') }}</flux:sidebar.item>
        <livewire:admin.sidebar-analytics />
        @can('inbox.view')
            @php($unreadSubmissions = \App\Models\Submission::query()->unread()->count())
            <flux:sidebar.item icon="inbox" :href="route('admin.inbox-index')" :current="request()->routeIs('admin.inbox-*')" :badge="$unreadSubmissions > 0 ? $unreadSubmissions : null" wire:navigate.hover>{{ __('Inbox') }}</flux:sidebar.item>
        @endcan
        @can('users.view')
            <flux:sidebar.item icon="users" :href="route('admin.users-index')" :current="request()->routeIs('admin.users-*')" wire:navigate.hover>{{ __('Users') }}</flux:sidebar.item>
        @endcan
        @can('pages.view')
            <flux:sidebar.item icon="cursor-arrow-ripple" :href="route('admin.pages-index')" :current="request()->routeIs('admin.pages-*')" wire:navigate.hover>{{ __('Pages') }}</flux:sidebar.item>
        @endcan
        <livewire:admin.sidebar-nav />
        @can('categories.view')
            <flux:sidebar.item icon="tag" :href="route('admin.categories-index')" :current="request()->routeIs('admin.categories-*')" wire:navigate.hover>{{ __('Categories') }}</flux:sidebar.item>
        @endcan
        <flux:sidebar.item icon="photo" class="cursor-pointer" x-on:click="Livewire.dispatch('select-media', { target: 'media-gallery', type: null, max: 50, media: null })">{{ __('Media') }}</flux:sidebar.item>
        @can('settings.view')
            <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings-general')" :current="request()->routeIs('admin.settings-*')" wire:navigate.hover>{{ __('Settings') }}</flux:sidebar.item>
        @endcan
    </flux:sidebar.nav>

    <flux:spacer />

    <flux:text class="text-xs" variant="subtle">
        {{ __('Made with') }} <a href="https://wire-up.dev" target="_blank">Wire-Up</a>
    </flux:text>
</flux:sidebar>