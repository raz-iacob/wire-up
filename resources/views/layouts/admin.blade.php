<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    
    @php
        $siteName = \App\Models\Settings::cached()?->title ?: config('app.name');
        $siteFavicon = \App\Services\SettingsService::current()->faviconUrl();
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow" />

        @if ($siteFavicon)
        <link rel="icon" href="{{ $siteFavicon }}" />
        @else
        <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon-96x96.png') }}" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/images/favicon.svg') }}" />
        <link rel="shortcut icon" href="{{ Vite::asset('resources/images/favicon.ico') }}" />
        <link rel="apple-touch-icon" sizes="180x180" href="{{ Vite::asset('resources/images/apple-touch-icon.png') }}" />
        @endif
        <meta name="apple-mobile-web-app-title" content="{{ $siteName }}" />

        @vite(['resources/css/admin.css', 'resources/js/admin.js'])

        {{-- Flux's recommended UI font (matches --font-sans in admin.css); loaded on every admin page for a consistent shell. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

        @fluxAppearance

        <title>{{ isset($title) ? "$title | " : '' }}{{ $siteName }}</title>
        @if (isset($description))
        <meta name="description" content="{{ $description }}">
        @endif
    </head>

    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <x-admin.sidebar />

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

        <flux:main class="min-w-0">
            {{ $slot }}
        </flux:main>

        @persist('media-library')
        <livewire:admin.media-library />
        @endpersist

        @persist('toast')
        <flux:toast.group expanded position="bottom end">
            <flux:toast />
        </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>