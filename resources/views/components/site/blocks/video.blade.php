@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $aspect = $content['aspect'] ?? '16:9';
    $aspect = in_array($aspect, ['16:9', '9:16', '4:3'], true) ? $aspect : '16:9';
    $autoplay = (bool) ($content['autoplay'] ?? false);
    $loop = (bool) ($content['loop'] ?? false);
    $muted = $autoplay || (bool) ($content['muted'] ?? false);
    $controls = (bool) ($content['controls'] ?? true);

    $embed = $block->videoEmbed();
    $poster = $block->imageUrl('poster', ['w' => 1280]) ?? $block->posterUrl('video', ['w' => 1280]);

    $heading = strip_tags($heading) !== '' ? $heading : '';
    $hasHeading = $heading !== '' || strip_tags($intro) !== '';

    $aspectClass = match ($aspect) {
        '9:16' => 'aspect-[9/16] mx-auto w-full max-w-sm',
        '4:3' => 'aspect-[4/3]',
        default => 'aspect-video',
    };

    $iframeSrc = null;

    if ($embed !== null && $embed['kind'] === 'iframe') {
        if ($embed['provider'] === 'youtube') {
            $params = ['rel' => 0, 'modestbranding' => 1, 'playsinline' => 1];

            if ($autoplay) {
                $params['autoplay'] = 1;
            }
            if ($muted) {
                $params['mute'] = 1;
            }
            if ($loop) {
                $params['loop'] = 1;
                $params['playlist'] = $embed['id'];
            }
            if (! $controls) {
                $params['controls'] = 0;
            }

            $iframeSrc = 'https://www.youtube-nocookie.com/embed/'.$embed['id'].'?'.http_build_query($params);
        } else {
            $params = ['dnt' => 1];

            if ($autoplay) {
                $params['autoplay'] = 1;
            }
            if ($muted) {
                $params['muted'] = 1;
            }
            if ($loop) {
                $params['loop'] = 1;
            }
            if (! $controls) {
                $params['controls'] = 0;
            }

            $iframeSrc = 'https://player.vimeo.com/video/'.$embed['id'].'?'.http_build_query($params);
        }
    }
@endphp

<section @class([
    'w-full',
    'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
    ($pad ?? 'py-16') => $hasBg,
])>
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
        @if ($hasHeading)
            <div class="mb-8">
                @if ($heading !== '')
                    <div class="tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                @endif
                @if (strip_tags($intro) !== '')
                    <div class="mt-3 leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                @endif
            </div>
        @endif

        @if ($embed !== null)
            <div class="{{ $aspectClass }} overflow-hidden rounded-(--wire-radius) bg-black shadow-sm">
                @if ($embed['kind'] === 'iframe')
                    <iframe
                        src="{{ $iframeSrc }}"
                        class="size-full"
                        loading="lazy"
                        title="{{ $heading !== '' ? strip_tags($heading) : __('Video') }}"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen
                    ></iframe>
                @else
                    <video
                        class="size-full object-contain"
                        @if ($controls) controls @endif
                        @if ($autoplay) autoplay @endif
                        @if ($loop) loop @endif
                        @if ($muted) muted @endif
                        @if ($poster) poster="{{ $poster }}" @endif
                        playsinline
                        preload="metadata"
                    >
                        <source src="{{ $embed['src'] }}" />
                    </video>
                @endif
            </div>
        @endif
    </div>
</section>
