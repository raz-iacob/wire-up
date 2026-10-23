@props([
    'record',
    'showImage' => true,
    'layout' => 'grid',
    'fields' => [],
    'cardBg' => '',
    'cardText' => '',
    'dimText' => true,
    'fieldPosition' => 'footer',
    'imageShape' => 'landscape',
    'imageFit' => 'cover',
])

@php
    $linked = $record->hasDetailPage();
    $url = $linked ? $record->getUrl() : null;
    $heading = $record->displayHeading();
    $excerpt = $record->displayExcerpt();
    $image = $showImage ? $record->primaryImageUrl($layout === 'list' ? 400 : 900) : null;

    $meta = [];
    $badges = [];
    foreach ($fields as $field) {
        if (\App\Enums\FieldType::tryFrom($field['type'] ?? '') === \App\Enums\FieldType::BOOLEAN) {
            if ($record->fieldValue($field['key'], (bool) ($field['translatable'] ?? false))) {
                $badges[] = $record->recordType->fieldLabel($field);
            }

            continue;
        }

        $value = $record->columnValue($field, publishedOnly: true);

        if ($value !== '' && $value !== '—') {
            $meta[] = $value;
        }
    }

    $badgeClass = 'rounded-full bg-current/10 px-2.5 py-1 text-xs font-bold text-(--wire-accent)';

    $cardStyle = \App\Services\BlockColor::style([
        'background-color' => $cardBg,
        'color' => $cardText,
    ]);

    $excerptClass = $dimText ? 'leading-(--wire-body-leading) opacity-80' : 'leading-(--wire-body-leading)';
    $fieldsUnderTitle = $fieldPosition === 'under-title';
@endphp

@if ($layout === 'list')
    <{{ $linked ? 'a' : 'div' }} @if ($linked) href="{{ $url }}" @endif class="group flex items-start gap-6 py-6">
        @if ($image)
            <x-site.blocks.card-image
                :src="$image"
                :alt="$heading"
                shape="none"
                :fit="$imageFit"
                class="size-32 shrink-0 rounded-(--wire-radius) sm:size-44"
            />
        @endif
        <div class="min-w-0">
            <h3 class="text-lg font-semibold tracking-tight group-hover:text-(--wire-accent)">{{ $heading }}</h3>
            @if ($badges !== [])
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($badges as $badge)
                        <span class="{{ $badgeClass }}">{{ $badge }}</span>
                    @endforeach
                </div>
            @endif
            @if ($fieldsUnderTitle && $meta !== [])
                <p class="mt-2 font-medium">{{ implode(' · ', $meta) }}</p>
            @endif
            @if ($excerpt !== '')
                <p class="mt-1 {{ $excerptClass }}">{{ $excerpt }}</p>
            @endif
            @if (! $fieldsUnderTitle && $meta !== [])
                <p class="mt-2 font-medium">{{ implode(' · ', $meta) }}</p>
            @endif
        </div>
    </{{ $linked ? 'a' : 'div' }}>
@else
    <{{ $linked ? 'a' : 'div' }}
        @if ($linked) href="{{ $url }}" @endif
        class="group wire-card wire-card--collection flex h-full flex-col overflow-hidden rounded-(--wire-radius) shadow-sm transition hover:shadow-md"
        @if ($cardStyle !== '') style="{{ $cardStyle }}" @endif
    >
        @if ($image)
            <x-site.blocks.card-image
                :src="$image"
                :alt="$heading"
                :shape="$imageShape"
                :fit="$imageFit"
                class="w-full"
            />
        @endif
        <div class="flex grow flex-col gap-2 p-5">
            <h3 class="text-lg font-semibold tracking-tight group-hover:text-(--wire-accent)">{{ $heading }}</h3>
            @if ($badges !== [])
                <div class="flex flex-wrap gap-2">
                    @foreach ($badges as $badge)
                        <span class="{{ $badgeClass }}">{{ $badge }}</span>
                    @endforeach
                </div>
            @endif
            @if ($fieldsUnderTitle && $meta !== [])
                <p class="font-medium">{{ implode(' · ', $meta) }}</p>
            @endif
            @if ($excerpt !== '')
                <p class="{{ $excerptClass }}">{{ $excerpt }}</p>
            @endif
            @if (! $fieldsUnderTitle && $meta !== [])
                <p class="mt-auto pt-1 font-medium">{{ implode(' · ', $meta) }}</p>
            @endif
        </div>
    </{{ $linked ? 'a' : 'div' }}>
@endif
