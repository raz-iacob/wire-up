<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow" />

        <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon-96x96.png') }}" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/images/favicon.svg') }}" />
        <link rel="shortcut icon" href="{{ Vite::asset('resources/images/favicon.ico') }}" />
        <link rel="apple-touch-icon" sizes="180x180" href="{{ Vite::asset('resources/images/apple-touch-icon.png') }}" />
        <meta name="apple-mobile-web-app-title" content="Wire↑" />

        @vite(['resources/css/app.css', 'resources/js/admin.js'])
        
        @fluxAppearance

        <title>{{ isset($title) ? "$title | " : '' }}{{ config('app.name') }}</title>
        @if (isset($description))
        <meta name="description" content="{{ $description }}">
        @endif
    </head>

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

        @persist('media-library')
        <livewire:media-library />
        @endpersist

        @persist('toast')
        <flux:toast.group expanded position="top end">
            <flux:toast />
        </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>