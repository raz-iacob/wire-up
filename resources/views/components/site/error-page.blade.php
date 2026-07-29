@props(['code', 'title', 'message'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="[scrollbar-gutter:stable] scroll-smooth">
    <x-site.head :title="$title" />
    <body class="bg-(--wire-body-bg) font-(family-name:--wire-body-font) text-(--wire-body-text) antialiased">
        <div class="flex min-h-screen flex-col">
            <livewire:site.header />

            <main class="flex flex-1 items-center justify-center px-(--wire-gutter) py-16">
                <div class="max-w-md text-center">
                    <div class="text-xs font-bold tracking-[0.2em] text-(--wire-accent) uppercase">
                        {{ __('Error') }} {{ $code }}
                    </div>
                    <h1 class="mt-4 text-[calc(var(--wire-heading-size)*1.5)] font-normal">{{ $title }}</h1>
                    <p class="mt-3 leading-relaxed text-(--wire-muted)">{{ $message }}</p>
                </div>
            </main>
        </div>

        @fluxScripts
    </body>
</html>
