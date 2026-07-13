@props(['code', 'title', 'message'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth [scrollbar-gutter:stable]">
    <x-site.head :title="$title" />
    <body class="antialiased font-(family-name:--wire-body-font) bg-(--wire-body-bg) text-(--wire-body-text)">
        <div class="flex min-h-screen flex-col">
            <livewire:site.header />

            <main class="flex flex-1 items-center justify-center px-(--wire-gutter) py-16">
                <div class="max-w-md text-center">
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-(--wire-accent)">{{ __('Error') }} {{ $code }}</div>
                    <h1 class="mt-4 text-[calc(var(--wire-heading-size)*1.5)] font-normal">{{ $title }}</h1>
                    <p class="mt-3 leading-relaxed text-(--wire-muted)">{{ $message }}</p>
                </div>
            </main>
        </div>

        @fluxScripts
    </body>
</html>
