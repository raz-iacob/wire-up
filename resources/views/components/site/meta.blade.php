@props(['page' => null, 'title' => null, 'description' => '', 'site', 'siteName'])

@php
    $seo = \App\Services\SeoService::current();
    $canonical = $seo->canonicalUrl();
    $metaDescription = $seo->description($page, (string) $description);
    $ogImage = $seo->ogImageUrl($page);
    $ogTitle = $title ?: $siteName;
    $ogLocale = str_replace('-', '_', app()->getLocale());
    $themeColor = $site->color('background');
    $darkThemeColor = $site->darkThemeColors()['background'] ?? null;
@endphp

<meta name="robots" content="{{ $seo->robots($page) }}">
<link rel="canonical" href="{{ $canonical }}">
@if ($metaDescription !== '')
<meta name="description" content="{{ $metaDescription }}">
@endif

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $ogTitle }}">
@if ($metaDescription !== '')
<meta property="og:description" content="{{ $metaDescription }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $ogLocale }}">
@if ($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif

<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
@if ($metaDescription !== '')
<meta name="twitter:description" content="{{ $metaDescription }}">
@endif
@if ($ogImage)
<meta name="twitter:image" content="{{ $ogImage }}">
@endif

@foreach ($seo->hreflangAlternates($page) as $code => $href)
<link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}" />
@endforeach

@if ($themeColor && $darkThemeColor)
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ $themeColor }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ $darkThemeColor }}">
@elseif ($themeColor)
<meta name="theme-color" content="{{ $themeColor }}">
@endif

<script type="application/ld+json">{!! json_encode($seo->jsonLd($page), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
