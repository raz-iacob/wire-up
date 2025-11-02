<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <x-head :title="isset($title) ? $title : null" />
    <body>
        <flux:main>
            {{ $slot }}
        </flux:main>
        @fluxScripts
    </body>
</html>
