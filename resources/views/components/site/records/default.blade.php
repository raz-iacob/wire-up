@props(['record'])

@php
    $imageUrl = function (\App\Models\Media $media, int $width): string {
        $cropSet = is_array($media->pivot->crop ?? null) ? $media->pivot->crop : [];
        $crop = is_array($cropSet['default'] ?? null) ? $cropSet['default'] : [];
        $options = ["w={$width}", 'q=80', 'fm=jpg'];

        if (($crop['crop_w'] ?? 0) > 0 && ($crop['crop_h'] ?? 0) > 0) {
            $options[] = sprintf('crop=%d-%d-%d-%d', $crop['crop_w'], $crop['crop_h'], $crop['crop_x'] ?? 0, $crop['crop_y'] ?? 0);
        }

        return \App\Services\ImageService::url(implode(',', $options), $media->source);
    };

    $photo = \Illuminate\Support\Arr::first($record->fieldMedia('photo', false));
    $heading = $record->fieldValue('heading', true) ?: $record->title;
    $overview = (string) ($record->fieldValue('overview', true) ?? '');
@endphp

<section class="w-full">
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter) py-16">
        <div @class(['md:flex md:items-start md:gap-10' => $photo])>
            @if ($photo)
                <div class="wire-card aspect-square w-full max-w-xs overflow-hidden rounded-[calc(var(--wire-radius)*1.5)] bg-(--wire-card-bg) md:order-last md:w-1/4 md:max-w-none md:shrink-0">
                    <img src="{{ $imageUrl($photo, 900) }}" alt="{{ $photo->alt_text ?? $heading }}" loading="lazy" class="size-full object-cover" />
                </div>
            @endif

            <div @class(['flex flex-col gap-5', 'mt-6 min-w-0 flex-1 md:mt-0' => $photo])>
                <h1 class="tracking-tight text-(length:--wire-heading-size)">{{ $heading }}</h1>

                @if (strip_tags($overview) !== '')
                    <div class="max-w-none leading-relaxed [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-4 *:first:mt-0 *:last:mb-0">
                        {!! $overview !!}
                    </div>
                @elseif ($record->description !== '')
                    <p class="leading-relaxed">{{ $record->description }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
