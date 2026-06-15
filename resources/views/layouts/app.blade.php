<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <x-site.head :title="isset($title) ? $title : null" :description="isset($description) ? $description : null" />
    <body
        class="antialiased font-(family-name:--wire-body-font) bg-(--wire-body-bg) text-(--wire-body-text)"
    >
        <div class="flex flex-col min-h-screen">
            <livewire:site.header />

            <main class="flex-1 overflow-y-auto flex flex-col">
                {{ $slot }}
            </main>

            <livewire:site.footer />
        </div>

        @stack('modals')

        @persist('toast')
        <flux:toast />
        @endpersist
        
        @fluxScripts
    </body>
</html>
