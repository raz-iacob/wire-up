@props(['page'])

@php
    $spacing = \App\Services\SettingsService::current()->blockSpacing();
    $gapClass = match ($spacing) {
        'small' => 'gap-12',
        'large' => 'gap-20',
        default => 'gap-16',
    };
    $bottomClass = match ($spacing) {
        'small' => 'mb-24',
        'large' => 'mb-40',
        default => 'mb-32',
    };
    $padClass = match ($spacing) {
        'small' => 'py-12',
        'large' => 'py-20',
        default => 'py-16',
    };
    $flushTopClass = match ($spacing) {
        'small' => '-mt-12',
        'large' => '-mt-20',
        default => '-mt-16',
    };
    $flushBottomClass = match ($spacing) {
        'small' => '-mb-12',
        'large' => '-mb-20',
        default => '-mb-16',
    };

    $blocks = $page->blocks;
    $lastIndex = $blocks->count() - 1;
@endphp

<article @class(['flex w-full flex-col', $gapClass, $bottomClass])>
    @foreach ($blocks as $index => $block)
        @php
            $anchor = $block->type->hasAnchor() ? trim((string) ($block->content['anchor'] ?? '')) : '';
            $isFullWidthHero = $block->type === \App\Enums\BlockType::HERO
                && ($block->content['width'] ?? 'full') !== 'container';
        @endphp
        <div @class([
            'scroll-mt-24' => $anchor !== '',
            $flushTopClass => $isFullWidthHero && $index > 0,
            $flushBottomClass => $isFullWidthHero && $index < $lastIndex,
        ]) @if ($anchor !== '') id="{{ $anchor }}" @endif>
            @includeIf($block->type->frontendView(), ['block' => $block, 'pad' => $padClass])
        </div>
    @endforeach
</article>
