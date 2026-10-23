@props(['title', 'excerpt' => '', 'url', 'image' => null, 'layout' => 'grid', 'imageShape' => 'landscape', 'imageFit' => 'cover'])

@if ($layout === 'list')
    <a href="{{ $url }}" class="group flex items-start gap-6 py-6">
        @if ($image)
            <x-site.blocks.card-image
                :src="$image"
                :alt="$title"
                shape="none"
                :fit="$imageFit"
                class="size-32 shrink-0 rounded-(--wire-radius) sm:size-44"
            />
        @endif
        <div class="min-w-0">
            <h3 class="text-lg font-semibold tracking-tight group-hover:text-(--wire-accent)">{{ $title }}</h3>
            @if ($excerpt !== '')
                <p class="mt-1 leading-(--wire-body-leading) opacity-80">{{ $excerpt }}</p>
            @endif
        </div>
    </a>
@else
    <a
        href="{{ $url }}"
        class="group wire-card wire-card--search flex h-full flex-col overflow-hidden rounded-(--wire-radius) shadow-sm transition hover:shadow-md"
    >
        @if ($image)
            <x-site.blocks.card-image
                :src="$image"
                :alt="$title"
                :shape="$imageShape"
                :fit="$imageFit"
                class="w-full"
            />
        @endif
        <div class="flex grow flex-col gap-2 p-5">
            <h3 class="text-lg font-semibold tracking-tight group-hover:text-(--wire-accent)">{{ $title }}</h3>
            @if ($excerpt !== '')
                <p class="leading-(--wire-body-leading) opacity-80">{{ $excerpt }}</p>
            @endif
        </div>
    </a>
@endif
