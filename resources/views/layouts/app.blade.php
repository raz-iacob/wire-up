<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    @php
        $siteLayout = array_merge([
            'hideHeader' => false,
            'hideFooter' => false,
            'backgroundColor' => null,
            'backgroundImage' => null,
            'backgroundFixed' => false,
            'customCss' => '',
            'sidebar' => ['menus' => []],
        ], $siteLayout ?? []);

        $bodyStyle = collect([
            $siteLayout['backgroundColor'] ? "background-color:{$siteLayout['backgroundColor']}" : null,
            $siteLayout['backgroundImage'] ? "background-image:url({$siteLayout['backgroundImage']})" : null,
            $siteLayout['backgroundImage'] && $siteLayout['backgroundFixed'] ? 'background-attachment:fixed' : null,
        ])->filter()->implode(';');

        $sidebarMenus = collect($siteLayout['sidebar']['menus'] ?? [])
            ->map(fn (string $key): ?array => \App\Services\SettingsService::current()->menuForDisplay($key))
            ->filter()
            ->values();
        $leftMenus = $sidebarMenus->filter(fn (array $menu): bool => $menu['display']['position'] === 'left')->values();
        $rightMenus = $sidebarMenus->filter(fn (array $menu): bool => $menu['display']['position'] === 'right')->values();
        $hasSidebar = $sidebarMenus->isNotEmpty();
    @endphp
    <x-site.head :title="isset($title) ? $title : null" :description="isset($description) ? $description : null" :custom-css="$siteLayout['customCss']" />
    <body
        @class([
            'antialiased font-(family-name:--wire-body-font) bg-(--wire-body-bg) text-(--wire-body-text)',
            'bg-cover bg-center bg-no-repeat' => $siteLayout['backgroundImage'],
        ])
        @if ($bodyStyle !== '') style="{{ $bodyStyle }}" @endif
    >
        <div class="flex flex-col min-h-screen">
            @unless ($siteLayout['hideHeader'])
                <livewire:site.header />
            @endunless

            <main @class(['flex-1 flex flex-col', 'overflow-y-auto' => ! $hasSidebar])>
                @if ($hasSidebar)
                    <div class="mx-auto flex w-full max-w-(--wire-container) flex-col gap-8 py-10 md:flex-row">
                        @if ($leftMenus->isNotEmpty())
                            <div class="flex shrink-0 flex-col gap-8 px-(--wire-gutter) md:w-1/6 md:pe-0">
                                @foreach ($leftMenus as $leftMenu)
                                    <x-site.sidebar :menu="$leftMenu" wire:key="sidebar-left-{{ $loop->index }}" />
                                @endforeach
                            </div>
                        @endif

                        <div class="min-w-0 flex-1 max-md:order-last">
                            {{ $slot }}
                        </div>

                        @if ($rightMenus->isNotEmpty())
                            <div class="flex shrink-0 flex-col gap-8 px-(--wire-gutter) md:w-1/6 md:ps-0">
                                @foreach ($rightMenus as $rightMenu)
                                    <x-site.sidebar :menu="$rightMenu" wire:key="sidebar-right-{{ $loop->index }}" />
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    {{ $slot }}
                @endif
            </main>

            @unless ($siteLayout['hideFooter'])
                <livewire:site.footer />
            @endunless
        </div>

        @stack('modals')

        @persist('toast')
        <flux:toast />
        @endpersist
        
        @fluxScripts
    </body>
</html>
