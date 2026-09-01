@props(['block', 'page' => null])

@php
    $content = $block->content ?? [];
    $headingLevel = \App\Services\BlockHeading::level(
        $content['headingLevel'] ?? null,
        $page instanceof \App\Models\Record ? 'h2' : 'h1',
    );
    $heading = $block->text('heading');
    $subheading = $block->text('subheading');

    $bg = $content['background'] ?? [];
    $type = $bg['type'] ?? 'image';
    $imageDesktop = $type === 'image' ? $block->imageUrl('background.image', ['w' => 1920, 'h' => 1080], 'desktop') : null;
    $imageMobile = $type === 'image' ? $block->imageUrl('background.image', ['w' => 1080, 'h' => 1350], 'mobile') : null;
    $bgVideo = $type === 'video' ? $block->fileUrl('background.video') : null;
    $bgVideoPoster = $type === 'video' ? $block->posterUrl('background.video', ['w' => 1920, 'h' => 1080]) : null;

    $align = $content['align'] ?? 'center';
    $valign = $content['verticalAlign'] ?? 'center';
    $width = $content['width'] ?? 'full';
    $height = $content['height'] ?? 'auto';

    $customHeight = is_numeric($content['customHeight'] ?? null) ? (int) $content['customHeight'] : null;
    $customHeight = $customHeight !== null && $customHeight >= 120 && $customHeight <= 2000 ? $customHeight : null;
    $isCustomHeight = $height === 'custom' && $customHeight !== null;

    $isCover = in_array($height, ['large', 'screen'], true) || $isCustomHeight;

    $headingSize = is_numeric($content['headingSize'] ?? null) ? (int) $content['headingSize'] : null;
    $headingSize = $headingSize !== null && $headingSize >= 12 && $headingSize <= 160 ? $headingSize : null;

    $subheadingSize = is_numeric($content['subheadingSize'] ?? null) ? (int) $content['subheadingSize'] : null;
    $subheadingSize = $subheadingSize !== null && $subheadingSize >= 10 && $subheadingSize <= 96 ? $subheadingSize : null;

    $dimSubheading = (bool) ($content['dimSubheading'] ?? true);

    $textStyle = function (?string $color, ?int $size): string {
        $parts = [];

        if (\App\Services\BlockColor::safe($color) !== '') {
            $parts[] = 'color:'.\App\Services\BlockColor::safe($color);
        }

        if ($size !== null) {
            $parts[] = "font-size:{$size}px";
        }

        return implode(';', $parts);
    };
    $isContainer = $width === 'container';
    $overlayContent = ! $isCover && ($imageDesktop || $bgVideo);

    $headingColor = ($content['headingColor'] ?? null) ?: null;
    $subheadingColor = ($content['subheadingColor'] ?? null) ?: null;

    $styles = ['color:var(--wire-hero-text, var(--wire-header-text))'];

    if ($isCustomHeight) {
        $styles[] = "min-height:{$customHeight}px";
    }

    if ($type === 'color') {
        $gradient = $bg['gradient'] ?? [];
        $direction = ($gradient['direction'] ?? 'to-b') === 'to-r' ? 'to right' : 'to bottom';
        $start = ($gradient['start'] ?? null) ?: 'var(--wire-hero-bg, var(--wire-header-bg))';
        $end = ($gradient['end'] ?? null) ?: 'var(--wire-hero-bg, var(--wire-header-bg))';
        $styles[] = "background-image:linear-gradient({$direction}, {$start}, {$end})";
    } else {
        $styles[] = 'background-color:var(--wire-hero-bg, var(--wire-header-bg))';
    }

    $defaultBg = ['ctaPrimary' => 'var(--wire-primary-bg)', 'ctaSecondary' => 'var(--wire-secondary-bg)'];
    $defaultText = ['ctaPrimary' => 'var(--wire-primary-text)', 'ctaSecondary' => 'var(--wire-secondary-text)'];
    $defaultBorder = ['ctaPrimary' => 'var(--wire-primary-border)', 'ctaSecondary' => 'var(--wire-secondary-border)'];

    $ctas = collect(['ctaPrimary', 'ctaSecondary'])
        ->map(fn (string $key): array => [
            'text' => $block->text("{$key}.text"),
            'url' => $block->ctaUrl($key),
            'newTab' => $block->ctaOpensNewTab($key),
            'enabled' => (bool) ($content[$key]['enabled'] ?? false),
            'bg' => ($content[$key]['bg'] ?? null) ?: $defaultBg[$key],
            'fg' => ($content[$key]['textColor'] ?? null) ?: $defaultText[$key],
            'border' => $defaultBorder[$key],
        ])
        ->filter(fn (array $cta): bool => $cta['enabled'] && $cta['text'] !== '' && $cta['url'] !== null)
        ->values();
@endphp

<section
    @class([
        'relative w-full overflow-hidden' => true,
        'mx-auto max-w-(--wire-container) my-12 md:my-16' => $isContainer,
        'flex min-h-[70vh]' => $height === 'large',
        'flex min-h-svh' => $height === 'screen',
        'flex' => $isCustomHeight,
    ])
    style="{{ implode(';', $styles) }}"
>
    @if ($imageDesktop)
        <picture class="contents">
            @if ($imageMobile)
                <source media="(max-width: 767px)" srcset="{{ $imageMobile }}" />
            @endif
            <img
                src="{{ $imageDesktop }}"
                alt="{{ $block->imageAlt('background.image') }}"
                fetchpriority="high"
                @class([
                    'absolute inset-0 size-full object-cover' => $isCover,
                    'block w-full max-md:absolute max-md:inset-0 max-md:size-full max-md:object-cover' => $overlayContent,
                ])
            />
        </picture>
    @endif

    @if ($bgVideo)
        <video
            @class([
                'absolute inset-0 size-full object-cover' => $isCover,
                'block w-full max-md:absolute max-md:inset-0 max-md:size-full max-md:object-cover' => $overlayContent,
            ])
            autoplay
            loop
            muted
            playsinline
            @if ($bgVideoPoster) poster="{{ $bgVideoPoster }}" @endif
            preload="metadata"
        >
            <source src="{{ $bgVideo }}" />
        </video>
    @endif

    <div @class([
        'z-10 mx-auto flex w-full max-w-(--wire-container) flex-col gap-5 px-(--wire-gutter) py-24',
        'md:py-28' => ! $isCover,
        'absolute inset-0 max-md:relative max-md:inset-auto' => $overlayContent,
        'relative' => ! $overlayContent,
        'items-start text-left' => $align === 'left',
        'items-center text-center' => $align === 'center',
        'items-end text-right' => $align === 'right',
        'justify-start' => $valign === 'top',
        'justify-center' => $valign === 'center',
        'justify-end' => $valign === 'bottom',
    ])>
        @if ($heading)
            <div
                @class([
                    '[&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline max-w-3xl font-bold tracking-tight',
                    'text-[length:calc(var(--wire-heading-size)*1.2)] md:text-[length:calc(var(--wire-heading-size)*1.5)]' => $headingSize === null,
                ])
                @if ($textStyle($headingColor, $headingSize) !== '')
                    style="{{ $textStyle($headingColor, $headingSize) }}"
                @endif
            >
                <x-site.blocks.heading :html="$heading" :level="$headingLevel" />
            </div>
        @endif

        @if ($subheading)
            <div
                @class([
                    '[&_a]:text-(--wire-accent) [&_a]:underline max-w-2xl',
                    'text-[length:calc(var(--wire-body-size)*1.1)] md:text-[length:calc(var(--wire-body-size)*1.25)]' => $subheadingSize === null,
                    'opacity-90' => $dimSubheading,
                ])
                @if ($textStyle($subheadingColor, $subheadingSize) !== '')
                    style="{{ $textStyle($subheadingColor, $subheadingSize) }}"
                @endif
            >
                {!! $subheading !!}
            </div>
        @endif

        @if ($ctas->isNotEmpty())
            <div @class([
                'mt-2 flex flex-wrap gap-4',
                'justify-start' => $align === 'left',
                'justify-center' => $align === 'center',
                'justify-end' => $align === 'right',
            ])>
                @foreach ($ctas as $cta)
                    <a
                        href="{{ $cta['url'] }}"
                        @if ($cta['newTab']) target="_blank" rel="noopener noreferrer" @endif
                        class="wire-btn inline-flex items-center justify-center rounded-(--wire-btn-radius) px-6 py-3 text-base font-medium transition hover:opacity-90"
                        style="background-color:{{ $cta['bg'] }};color:{{ $cta['fg'] }};--wire-btn-border:{{ $cta['border'] }}"
                    >{{ $cta['text'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</section>
