@props(['title' => null])

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/images/favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ Vite::asset('resources/images/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ Vite::asset('resources/images/apple-touch-icon.png') }}" />
    <meta name="apple-mobile-web-app-title" content="Wire↑" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @fluxAppearance

    <title>{{ isset($title) ? "$title | " : '' }}{{ config('app.name') }}</title>
</head>