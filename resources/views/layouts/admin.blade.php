<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <x-head :title="isset($title) ? $title : null" />
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <x-admin-sidebar />

        <flux:header class="md:hidden">
            <div class="flex items-center gap-4">
                <flux:sidebar.toggle class="md:hidden" icon="bars-2" inset="left" />

                <flux:heading>{{ $title ?? '' }}</flux:heading>
            </div>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :avatar="auth()->user()->photo_url" :initials="auth()->user()->initials" :chevron="false" />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar 
                                    size="sm" 
                                    :src="auth()->user()->photo_url" 
                                    :name="auth()->user()->name" 
                                    :initials="auth()->user()->initials" 
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('admin.account-profile')" icon="user" iconVariant="outline" wire:navigate>
                            {{ __('Account') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full"
                        >
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @persist('toast')
        <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>