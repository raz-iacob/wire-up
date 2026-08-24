<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 1200px;
            height: 630px;
        }

        body {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 72px;
            background: {{ $background }};
            color: {{ $foreground }};
            font-family: {{ $font }};
            -webkit-font-smoothing: antialiased;
        }

        .accent {
            width: 96px;
            height: 10px;
            border-radius: 999px;
            background: {{ $accent }};
        }

        .title {
            font-size: {{ $titleSize }}px;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -0.02em;
            overflow-wrap: anywhere;
        }

        .foot {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 28px;
            font-weight: 600;
            opacity: 0.75;
        }

        .foot img {
            max-height: 56px;
            max-width: 320px;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <div class="accent"></div>

    <div class="title">{{ $title }}</div>

    <div class="foot">
        @if ($logo !== null)
            <img src="{{ $logo }}" alt="" />
        @else
            <span>{{ $brand }}</span>
        @endif
    </div>
</body>
</html>
