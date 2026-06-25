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
        ], $siteLayout ?? []);

        $bodyStyle = collect([
            $siteLayout['backgroundColor'] ? "background-color:{$siteLayout['backgroundColor']}" : null,
            $siteLayout['backgroundImage'] ? "background-image:url({$siteLayout['backgroundImage']})" : null,
            $siteLayout['backgroundImage'] && $siteLayout['backgroundFixed'] ? 'background-attachment:fixed' : null,
        ])->filter()->implode(';');
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

            <main class="flex-1 overflow-y-auto flex flex-col">
                {{ $slot }}
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
