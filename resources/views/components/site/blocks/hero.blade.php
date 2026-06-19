@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $subheading = $block->text('subheading');

    $bg = $content['background'] ?? [];
    $type = $bg['type'] ?? 'image';
    $image = $type === 'image' ? $block->imageUrl('background.image', ['w' => 1920, 'h' => 1080]) : null;

    $align = $content['align'] ?? 'center';
    $valign = $content['verticalAlign'] ?? 'center';
    $width = $content['width'] ?? 'full';
    $height = $content['height'] ?? 'auto';

    $isCover = in_array($height, ['large', 'screen'], true);
    $isContainer = $width === 'container';
    $overlayContent = ! $isCover && $image;

    $headingColor = ($content['headingColor'] ?? null) ?: null;
    $subheadingColor = ($content['subheadingColor'] ?? null) ?: null;

    $headingStyle = 'font-size:calc(var(--wire-heading-size, 1.5rem) * 1.5)'.($headingColor ? ";color:{$headingColor}" : '');
    $subheadingStyle = 'font-size:calc(var(--wire-body-size, 0.875rem) * 1.25)'.($subheadingColor ? ";color:{$subheadingColor}" : '');

    $styles = ['color:var(--wire-header-text)'];

    if ($type === 'color') {
        $gradient = $bg['gradient'] ?? [];
        $direction = ($gradient['direction'] ?? 'to-b') === 'to-r' ? 'to right' : 'to bottom';
        $start = ($gradient['start'] ?? null) ?: 'var(--wire-header-bg)';
        $end = ($gradient['end'] ?? null) ?: 'var(--wire-header-bg)';
        $styles[] = "background-image:linear-gradient({$direction}, {$start}, {$end})";
    } else {
        $styles[] = 'background-color:var(--wire-header-bg)';
    }

    $defaultBg = ['ctaPrimary' => 'var(--wire-primary-bg)', 'ctaSecondary' => 'var(--wire-secondary-bg)'];
    $defaultText = ['ctaPrimary' => 'var(--wire-primary-text)', 'ctaSecondary' => 'var(--wire-secondary-text)'];

    $ctas = collect(['ctaPrimary', 'ctaSecondary'])
        ->map(fn (string $key): array => [
            'text' => $block->text("{$key}.text"),
            'url' => $block->ctaUrl($key),
            'newTab' => $block->ctaOpensNewTab($key),
            'enabled' => (bool) ($content[$key]['enabled'] ?? false),
            'bg' => ($content[$key]['bg'] ?? null) ?: $defaultBg[$key],
            'fg' => ($content[$key]['textColor'] ?? null) ?: $defaultText[$key],
        ])
        ->filter(fn (array $cta): bool => $cta['enabled'] && $cta['text'] !== '' && $cta['url'] !== null)
        ->values();
@endphp

<section
    @class([
        'relative w-full overflow-hidden' => true,
        'mx-auto max-w-7xl my-12 md:my-16' => $isContainer,
        'flex min-h-[70vh]' => $height === 'large',
        'flex min-h-svh' => $height === 'screen',
    ])
    style="{{ implode(';', $styles) }}"
>
    @if ($image)
        <img
            src="{{ $image }}"
            alt="{{ $block->imageAlt('background.image') }}"
            fetchpriority="high"
            @class([
                'absolute inset-0 size-full object-cover' => $isCover,
                'block w-full' => ! $isCover,
            ])
        />
    @endif

    <div @class([
        'z-10 mx-auto flex w-full max-w-7xl flex-col gap-5 px-6 py-20',
        'md:py-28' => ! $isCover,
        'absolute inset-0' => $overlayContent,
        'relative' => ! $overlayContent,
        'items-start text-left' => $align === 'left',
        'items-center text-center' => $align === 'center',
        'items-end text-right' => $align === 'right',
        'justify-start' => $valign === 'top',
        'justify-center' => $valign === 'center',
        'justify-end' => $valign === 'bottom',
    ])>
        @if ($heading)
            <h2 class="max-w-3xl font-bold tracking-tight" style="{{ $headingStyle }}">{{ $heading }}</h2>
        @endif

        @if ($subheading)
            <div class="max-w-2xl opacity-90 [&_a]:underline" style="{{ $subheadingStyle }}">{!! $subheading !!}</div>
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
                        class="inline-flex items-center justify-center rounded-md px-6 py-3 text-base font-medium transition hover:opacity-90"
                        style="background-color:{{ $cta['bg'] }};color:{{ $cta['fg'] }}"
                    >{{ $cta['text'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</section>
