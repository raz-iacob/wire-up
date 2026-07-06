@props(['record', 'showImage' => true, 'layout' => 'grid', 'fields' => []])

@php
    $url = $record->getUrl();
    $heading = $record->displayHeading();
    $excerpt = $record->displayExcerpt();
    $image = $showImage ? $record->primaryImageUrl($layout === 'list' ? 400 : 900) : null;

    $meta = [];
    foreach ($fields as $field) {
        $value = $record->columnValue($field);

        if ($value !== '' && $value !== '—') {
            $meta[] = $value;
        }
    }
@endphp

@if ($layout === 'list')
    <a href="{{ $url }}" class="group flex items-start gap-6 py-6">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $heading }}" loading="lazy" class="size-32 shrink-0 rounded-(--wire-radius) object-cover sm:size-44" />
        @endif
        <div class="min-w-0">
            <h3 class="text-lg font-semibold tracking-tight group-hover:text-(--wire-accent)">{{ $heading }}</h3>
            @if ($excerpt !== '')
                <p class="mt-1 leading-relaxed opacity-80">{{ $excerpt }}</p>
            @endif
            @if ($meta !== [])
                <p class="mt-2 font-medium">{{ implode(' · ', $meta) }}</p>
            @endif
        </div>
    </a>
@else
    <a href="{{ $url }}" class="group wire-card flex h-full flex-col overflow-hidden rounded-(--wire-radius) shadow-sm transition hover:shadow-md">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $heading }}" loading="lazy" class="aspect-4/3 w-full object-cover" />
        @endif
        <div class="flex grow flex-col gap-2 p-5">
            <h3 class="text-lg font-semibold tracking-tight group-hover:text-(--wire-accent)">{{ $heading }}</h3>
            @if ($excerpt !== '')
                <p class="leading-relaxed opacity-80">{{ $excerpt }}</p>
            @endif
            @if ($meta !== [])
                <p class="mt-auto pt-1 font-medium">{{ implode(' · ', $meta) }}</p>
            @endif
        </div>
    </a>
@endif
