@props(['title' => null, 'description' => null])

@php
    $siteSettings = \App\Models\Settings::cached();
    $siteName = $siteSettings?->title ?: config('app.name');
    $siteFavicon = $siteSettings?->faviconUrl();
    $themeCss = $siteSettings?->themeCss();
    $googleFonts = $siteSettings?->googleFontsUrl();
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @if ($siteFavicon)
    <link rel="icon" href="{{ $siteFavicon }}" />
    @else
    <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/images/favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ Vite::asset('resources/images/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ Vite::asset('resources/images/apple-touch-icon.png') }}" />
    @endif
    <meta name="apple-mobile-web-app-title" content="{{ $siteName }}" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if ($googleFonts)
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $googleFonts }}" rel="stylesheet">
    @endif
    @if ($themeCss)
    <style>{!! $themeCss !!}</style>
    @endif

    @fluxAppearance

    <title>{{ isset($title) ? "$title | " : '' }}{{ $siteName }}</title>
    @if (isset($description))
    <meta name="description" content="{{ $description }}">
    @endif
</head>