@props(['title' => null, 'description' => null, 'customCss' => ''])

@php
    $site = \App\Services\SettingsService::current();
    $siteName = $site->title() ?: config('app.name');
    $favicon = $site->faviconUrl();
    $googleFonts = $site->googleFontsUrl();
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @if ($site->noindex())
    <meta name="robots" content="noindex, nofollow">
    @endif

    @if ($favicon)
    <link rel="icon" href="{{ $favicon }}" />
    @else
    <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/images/favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ Vite::asset('resources/images/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ Vite::asset('resources/images/apple-touch-icon.png') }}" />
    @endif
    <meta name="apple-mobile-web-app-title" content="{{ $siteName }}" />

    @vite(['resources/css/site.css', 'resources/js/app.js'])

    @if ($gaId = $site->googleAnalyticsId())
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@js($gaId));</script>
    @endif

    @if ($site->headScripts() !== '')
    {!! $site->headScripts() !!}
    @endif

    @if ($googleFonts)
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $googleFonts }}" rel="stylesheet">
    @endif
    <style>{!! $site->themeCss() !!}</style>
    @if ($site->customCss() !== '')
    <style>{!! $site->customCss() !!}</style>
    @endif
    @if ($customCss !== '')
    <style>{!! $customCss !!}</style>
    @endif

    <title>{{ $title ? "$title | " : '' }}{{ $siteName }}</title>
    @if ($description)
    <meta name="description" content="{{ $description }}">
    @endif
</head>
