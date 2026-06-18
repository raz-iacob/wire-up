@props(['block'])

@php
    $body = $block->text('body');
    $image = $block->imageUrl('image', ['w' => 1200]);
    $reverse = (bool) ($block->content['reverseLayout'] ?? false);
@endphp

<section class="mx-auto max-w-7xl px-6 py-12 md:py-16">
    <div @class([
        'md:grid md:grid-cols-2 md:items-center md:gap-10' => $image,
    ])>
        <div class="max-w-none leading-relaxed [&_a]:underline [&_h1]:text-3xl [&_h2]:text-2xl [&_h3]:text-xl [&_:where(h1,h2,h3)]:font-semibold">
            {!! $body !!}
        </div>

        @if ($image)
            <div @class(['mt-6 md:mt-0', 'md:order-first' => ! $reverse])>
                <img
                    src="{{ $image }}"
                    alt="{{ $block->imageAlt('image') }}"
                    loading="lazy"
                    class="w-full rounded-lg object-cover"
                />
            </div>
        @endif
    </div>
</section>
