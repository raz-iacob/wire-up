@props(['block'])

@php
    $heading = $block->text('heading');
    $subheading = $block->text('subheading');
    $image = $block->imageUrl('image', ['w' => 1920, 'h' => 1080]);
    $align = $block->content['align'] ?? 'center';
@endphp

<section
    @class([
        'relative w-full bg-cover bg-center' => $image,
    ])
    @if ($image) style="background-image:url('{{ $image }}')" @endif
>
    @if ($image)
        <div class="absolute inset-0 bg-black/40"></div>
    @endif

    <div
        @class([
            'relative mx-auto flex max-w-7xl flex-col gap-4 px-6 py-20 md:py-28',
            'items-start text-left' => $align === 'left',
            'items-center text-center' => $align === 'center',
            'items-end text-right' => $align === 'right',
            'text-white' => $image,
            'text-(--wire-body-text)' => ! $image,
        ])
    >
        @if ($heading)
            <h1 class="max-w-3xl text-4xl font-bold tracking-tight md:text-5xl">{{ $heading }}</h1>
        @endif

        @if ($subheading)
            <p class="max-w-2xl text-lg opacity-90 md:text-xl">{{ $subheading }}</p>
        @endif
    </div>
</section>
