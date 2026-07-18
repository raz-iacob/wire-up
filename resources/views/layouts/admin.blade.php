<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    
    @php
        $site = \App\Services\SettingsService::current();
        $siteName = $site->title() ?: config('app.name');
        $siteFavicon = $site->faviconUrl();
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

        @stack('head')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

        @php
            $storedAppearance = data_get(auth()->user()?->metadata, 'appearance');
            $storedAppearance = in_array($storedAppearance, ['light', 'dark', 'system'], true) ? $storedAppearance : null;
        @endphp
        @if ($storedAppearance)
        <script>
            (function () {
                var preference = @json($storedAppearance);
                if (preference === 'system') {
                    localStorage.removeItem('flux.appearance');
                } else {
                    localStorage.setItem('flux.appearance', preference);
                }
            })();
        </script>
        @endif

        @fluxAppearance

        <title>{{ isset($title) ? "$title | " : '' }}{{ $siteName }}</title>
        @if (isset($description))
        <meta name="description" content="{{ $description }}">
        @endif
    </head>

    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <x-admin.sidebar />

        <flux:header sticky class="block! bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
            <flux:navbar class="md:hidden w-full">
                <flux:sidebar.toggle icon="bars-2" inset="left" />
                
                @yield('header-content')

                <flux:spacer />

                <flux:dropdown position="bottom" align="end">
                    <flux:profile
                        :avatar="auth()->user()->photo_url" 
                        :initials="auth()->user()->initials" 
                        :chevron="false"
                    />

                    <flux:navmenu class="max-w-48">

                        <div class="px-2 py-1.5">
                            <flux:text size="sm">{{ __('Signed in as') }}</flux:text>
                            <flux:heading class="mt-1! truncate">{{ auth()->user()->email }}</flux:heading>
                        </div>

                        <flux:navmenu.separator />

                        <flux:navmenu.item icon="user" href="{{ route('admin.account-profile') }}" iconVariant="outline" wire:navigate>{{ __('Account') }}</flux:navmenu.item>
                        <flux:navmenu.item icon="information-circle" href="{{ route('admin.help') }}" iconVariant="outline" wire:navigate>{{ __('Help') }}</flux:navmenu.item>

                        {{-- @if (session()->has('impersonate'))
                        <livewire:admin.components.stop-impersonating />
                        @endif --}}

                        <flux:navmenu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:navmenu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full"
                            >
                                {{ __('Log Out') }}
                            </flux:navmenu.item>
                        </form>
                    </flux:navmenu>
                </flux:dropdown>
            </flux:navbar>

            <flux:navbar scrollable class="hidden md:flex w-full">
                @yield('header-content')
                <flux:spacer />
                <flux:dropdown position="bottom" align="end">
                    <flux:profile 
                        size="sm" 
                        :name="auth()->user()->name"
                        :avatar="auth()->user()->photo_url" 
                        :initials="auth()->user()->initials" 
                    />

                    <flux:navmenu class="max-w-48">

                        <div class="px-2 py-1.5">
                            <flux:text size="sm">{{ __('Signed in as') }}</flux:text>
                            <flux:heading class="mt-1! truncate">{{ auth()->user()->email }}</flux:heading>
                        </div>

                        <flux:navmenu.separator />

                        <flux:navmenu.item icon="user" href="{{ route('admin.account-profile') }}" iconVariant="outline" wire:navigate>{{ __('Account') }}</flux:navmenu.item>
                        <flux:navmenu.item icon="information-circle" href="{{ route('admin.help') }}" iconVariant="outline" wire:navigate>{{ __('Help') }}</flux:navmenu.item>

                        {{-- @if (session()->has('impersonate'))
                        <livewire:admin.components.stop-impersonating />
                        @endif --}}

                        <flux:navmenu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:navmenu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full"
                            >
                                {{ __('Log Out') }}
                            </flux:navmenu.item>
                        </form>
                    </flux:navmenu>
                </flux:dropdown>
            </flux:navbar>
        </flux:header>

        <flux:main class="min-w-0">
            {{ $slot }}
        </flux:main>

        @persist('media-library')
        <livewire:admin.media-library />
        @endpersist

        @persist('record-library')
        <livewire:admin.record-library />
        @endpersist

        @can('assistant.use')
            @if (config('site.ai_api_key'))
                @persist('assistant')
                <livewire:admin.assistant />
                @endpersist
            @endif
        @endcan

        @persist('toast')
        <flux:toast.group expanded position="top center">
            <flux:toast />
        </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>