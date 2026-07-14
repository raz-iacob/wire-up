@props(['record'])

@php
    $settings = \App\Services\SettingsService::current();

    $imageUrl = function (\App\Models\Media $media, int $width): string {
        $cropSet = is_array($media->pivot->crop ?? null) ? $media->pivot->crop : [];
        $crop = is_array($cropSet['default'] ?? null) ? $cropSet['default'] : [];
        $options = ["w={$width}", 'q=80', 'fm=jpg'];

        if (($crop['crop_w'] ?? 0) > 0 && ($crop['crop_h'] ?? 0) > 0) {
            $options[] = sprintf('crop=%d-%d-%d-%d', $crop['crop_w'], $crop['crop_h'], $crop['crop_x'] ?? 0, $crop['crop_y'] ?? 0);
        }

        return \App\Services\ImageService::url(implode(',', $options), $media->source);
    };

    $galleryItems = collect($record->fieldMedia('gallery', false))
        ->map(function (\App\Models\Media $media) use ($imageUrl): array {
            $isVideo = $media->type === \App\Enums\MediaType::VIDEO;

            return [
                'type' => $isVideo ? 'video' : 'image',
                'poster' => $isVideo
                    ? ($media->thumbnail ? \App\Services\ImageService::url('w=900', $media->thumbnail) : \App\Services\ImageService::placeholder())
                    : $imageUrl($media, 900),
                'full' => $isVideo ? $media->url : $imageUrl($media, 1600),
                'thumb' => $isVideo
                    ? ($media->thumbnail ? \App\Services\ImageService::url('w=200', $media->thumbnail) : \App\Services\ImageService::placeholder())
                    : $imageUrl($media, 200),
                'alt' => $media->alt_text ?? '',
                'caption' => (string) (is_array($media->pivot->metadata ?? null) ? ($media->pivot->metadata['caption'] ?? '') : ''),
            ];
        })
        ->all();

    $hasGallery = $galleryItems !== [];

    $heading = $record->fieldValue('heading', true) ?: $record->title;
    $overview = (string) ($record->fieldValue('overview', true) ?? '');

    $price = $record->fieldValue('current_price', false);
    $compare = $record->fieldValue('regular_price', false);
    $hasDiscount = is_numeric($price) && is_numeric($compare) && (float) $compare > (float) $price && (float) $price > 0;
    $discountPct = $hasDiscount ? (int) round((1 - (float) $price / (float) $compare) * 100) : null;
@endphp

<section class="w-full">
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter) py-16">
        <div @class(['md:grid md:grid-cols-2 md:gap-10' => $hasGallery])>
            @if ($hasGallery)
                <div
                    x-data="{
                        active: 0,
                        open: false,
                        items: @js($galleryItems),
                        show() { this.open = true; document.body.style.overflow = 'hidden'; },
                        close() { this.open = false; document.body.style.overflow = ''; },
                        prev() { this.active = (this.active - 1 + this.items.length) % this.items.length; },
                        next() { this.active = (this.active + 1) % this.items.length; },
                        get current() { return this.items[this.active] || {}; },
                    }"
                    x-on:keydown.escape.window="close()"
                    x-on:keydown.arrow-left.window="prev()"
                    x-on:keydown.arrow-right.window="next()"
                >
                    <button
                        type="button"
                        x-on:click="show()"
                        x-swipe
                        x-on:swipe-left="next()"
                        x-on:swipe-right="prev()"
                        class="wire-card group relative block aspect-4/3 w-full cursor-zoom-in overflow-hidden rounded-[calc(var(--wire-radius)*1.5)] bg-(--wire-card-bg)"
                    >
                        <img :src="items[active].poster" :alt="items[active].alt" class="size-full object-contain" />
                        <template x-if="items[active].type === 'video'">
                            <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                <span class="flex size-14 items-center justify-center rounded-full bg-black/55 text-white"><flux:icon name="play" class="size-7" /></span>
                            </span>
                        </template>
                    </button>

                    @if (count($galleryItems) > 1)
                        <div class="mt-3 grid grid-cols-5 gap-2 sm:grid-cols-7">
                            @foreach ($galleryItems as $i => $item)
                                <button
                                    type="button"
                                    x-on:click="active = {{ $i }}"
                                    :class="active === {{ $i }} ? 'ring-2 ring-(--wire-accent) ring-offset-2' : 'opacity-70 hover:opacity-100'"
                                    class="wire-card relative overflow-hidden rounded-(--wire-radius) bg-(--wire-card-bg) transition"
                                    wire:key="product-thumb-{{ $i }}"
                                >
                                    <img src="{{ $item['thumb'] }}" alt="" class="aspect-square size-full object-contain" />
                                    @if ($item['type'] === 'video')
                                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                            <flux:icon name="play-circle" class="size-6 text-white drop-shadow" />
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div
                        x-cloak
                        x-show="open"
                        x-transition.opacity
                        x-swipe
                        x-on:swipe-left="next()"
                        x-on:swipe-right="prev()"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
                        x-on:click.self="close()"
                    >
                        <button type="button" class="absolute right-4 top-4 text-white/70 hover:text-white" x-on:click="close()" aria-label="{{ __('Close') }}"><flux:icon name="x-mark" class="size-8" /></button>

                        <template x-if="items.length > 1">
                            <button type="button" class="absolute left-2 text-white/70 hover:text-white md:left-6" x-on:click="prev()" aria-label="{{ __('Previous') }}"><flux:icon name="chevron-left" class="size-10" /></button>
                        </template>
                        <template x-if="items.length > 1">
                            <button type="button" class="absolute right-2 text-white/70 hover:text-white md:right-6" x-on:click="next()" aria-label="{{ __('Next') }}"><flux:icon name="chevron-right" class="size-10" /></button>
                        </template>

                        <div class="flex max-h-[90vh] w-full max-w-5xl flex-col items-center justify-center gap-3">
                            <template x-if="current.type === 'video'">
                                <video class="max-h-[80vh] w-auto rounded-[calc(var(--wire-radius)*1.5)]" controls autoplay :src="current.full" :poster="current.poster"></video>
                            </template>
                            <template x-if="current.type !== 'video'">
                                <img class="max-h-[80vh] w-auto rounded-[calc(var(--wire-radius)*1.5)] object-contain" :src="current.full" :alt="current.alt" />
                            </template>
                            <p class="text-center text-sm text-white/80" x-show="current.caption" x-text="current.caption"></p>
                        </div>
                    </div>
                </div>
            @endif

            <div @class(['flex flex-col gap-5', 'mt-6 md:mt-0' => $hasGallery])>
                <h1 class="tracking-tight text-(length:--wire-heading-size)">{{ $heading }}</h1>

                @if (strip_tags($overview) !== '')
                    <div class="max-w-none leading-relaxed [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-4 *:first:mt-0 *:last:mb-0">
                        {!! $overview !!}
                    </div>
                @endif

                @if (is_numeric($price))
                    <div class="mt-2 flex flex-col gap-1">
                        <div class="flex items-center gap-3">
                            <span class="font-(family-name:--wire-heading-font) tracking-tight text-(length:--wire-heading-size)">{{ $settings->formatMoney($price) }}</span>
                            @if ($hasDiscount)
                                <span class="rounded-full bg-current/10 px-2.5 py-1 text-sm font-bold text-(--wire-accent)">{{ $discountPct }}%</span>
                            @endif
                        </div>
                        @if ($hasDiscount)
                            <span class="opacity-50 line-through">{{ $settings->formatMoney($compare) }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
