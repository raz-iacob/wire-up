<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <style>
        :root{--bg:#ffffff;--text:#18181b;--muted:#71717a;--border:#e4e4e7;--accent:#18181b}
        @media (prefers-color-scheme: dark){:root{--bg:#0a0a0a;--text:#fafafa;--muted:#a1a1aa;--border:#27272a;--accent:#6366f1}}
        *{box-sizing:border-box;margin:0}
        body{min-height:100vh;display:flex;flex-direction:column;background:var(--bg);color:var(--text);font-family:ui-sans-serif,system-ui,-apple-system,sans-serif;-webkit-font-smoothing:antialiased}
        header{padding:1rem 1.5rem;border-bottom:1px solid var(--border);font-weight:700}
        header a{color:inherit;text-decoration:none}
        main{flex:1;display:flex;align-items:center;justify-content:center;padding:4rem 1.5rem;text-align:center}
        .code{font-size:.75rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--accent)}
        h1{font-size:2.25rem;font-weight:400;margin-top:1rem}
        p{color:var(--muted);line-height:1.6;margin-top:.75rem;max-width:26rem}
    </style>
</head>
<body>
    <header><a href="/">{{ config('app.name') }}</a></header>
    <main>
        <div>
            <div class="code">{{ __('Error') }} {{ $code }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
        </div>
    </main>
</body>
</html>
