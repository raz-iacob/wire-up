@props(['record'])

@php
    $imageUrl = function (\App\Models\Media $media, int $width): string {
        $cropSet = is_array($media->pivot->crop ?? null) ? $media->pivot->crop : [];
        $crop = is_array($cropSet['default'] ?? null) ? $cropSet['default'] : [];
        $options = ["w={$width}", 'q=80', 'fm=jpg'];

        if (($crop['crop_w'] ?? 0) > 0 && ($crop['crop_h'] ?? 0) > 0) {
            $options[] = sprintf('crop=%d-%d-%d-%d', $crop['crop_w'], $crop['crop_h'], $crop['crop_x'] ?? 0, $crop['crop_y'] ?? 0);
        }

        return route('image.show', ['options' => implode(',', $options), 'path' => $media->source]);
    };

    $photo = \Illuminate\Support\Arr::first($record->fieldMedia('photo', false));
    $heading = $record->fieldValue('heading', true) ?: $record->title;
    $overview = (string) ($record->fieldValue('overview', true) ?? '');
    $location = (string) ($record->fieldValue('location', false) ?? '');

    $starts = (string) ($record->fieldValue('starts_at', false) ?? '');
    $ends = (string) ($record->fieldValue('ends_at', false) ?? '');
    $startDate = $starts !== '' ? rescue(fn () => \Illuminate\Support\Carbon::parse($starts), null, false) : null;
    $endDate = $ends !== '' ? rescue(fn () => \Illuminate\Support\Carbon::parse($ends), null, false) : null;

    $when = '';
    if ($startDate !== null) {
        if ($endDate !== null && $endDate->isSameDay($startDate)) {
            $when = $startDate->translatedFormat('F j, Y · g:i A').' – '.$endDate->translatedFormat('g:i A');
        } elseif ($endDate !== null) {
            $when = $startDate->translatedFormat('F j, Y · g:i A').' – '.$endDate->translatedFormat('F j, Y · g:i A');
        } else {
            $when = $startDate->translatedFormat('F j, Y · g:i A');
        }
    }
@endphp

<section class="w-full">
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter) py-16">
        <div @class(['md:flex md:items-start md:gap-10' => $photo])>
            @if ($photo)
                <div class="wire-card aspect-square w-full max-w-xs overflow-hidden rounded-[calc(var(--wire-radius)*1.5)] bg-(--wire-card-bg) md:w-1/4 md:max-w-none md:shrink-0">
                    <img src="{{ $imageUrl($photo, 900) }}" alt="{{ $photo->alt_text ?? $heading }}" loading="lazy" class="size-full object-cover" />
                </div>
            @endif

            <div @class(['flex flex-col gap-5', 'mt-6 min-w-0 flex-1 md:mt-0' => $photo])>
                <h1 class="tracking-tight text-(length:--wire-heading-size)">{{ $heading }}</h1>

                @if ($when !== '' || mb_trim($location) !== '')
                    <div class="flex flex-col gap-2">
                        @if ($when !== '')
                            <span class="inline-flex items-center gap-2 font-medium">
                                <flux:icon name="calendar" class="size-5 shrink-0 text-(--wire-accent)" />
                                {{ $when }}
                            </span>
                        @endif
                        @if (mb_trim($location) !== '')
                            <span class="inline-flex items-center gap-2 font-medium">
                                <flux:icon name="map-pin" class="size-5 shrink-0 text-(--wire-accent)" />
                                {{ $location }}
                            </span>
                        @endif
                    </div>
                @endif

                @if (strip_tags($overview) !== '')
                    <div class="max-w-none leading-relaxed [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-4 *:first:mt-0 *:last:mb-0">
                        {!! $overview !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
