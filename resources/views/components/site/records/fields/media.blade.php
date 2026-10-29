@props(['record', 'field'])

@php
    $fieldType = \App\Enums\FieldType::from((string) $field['type']);
    $items = $record->fieldMedia((string) $field['key'], (bool) ($field['translatable'] ?? false));
@endphp

@if ($items !== [])
    <div class="flex flex-col gap-3" data-field="{{ $field['key'] }}">
        @foreach ($items as $media)
            @if ($fieldType === \App\Enums\FieldType::VIDEO)
                <video
                    controls
                    preload="metadata"
                    @if ($media->thumbnail) poster="{{ \App\Services\ImageService::url('w=1200', $media->thumbnail) }}" @endif
                    class="w-full rounded-(--wire-radius) bg-black"
                >
                    <source src="{{ $media->url }}" @if ($media->mime_type) type="{{ $media->mime_type }}" @endif />
                </video>
            @elseif ($fieldType === \App\Enums\FieldType::AUDIO)
                <audio controls preload="metadata" class="w-full" src="{{ $media->url }}"></audio>
            @else
                <a
                    href="{{ $media->download_url ?? $media->url }}"
                    download
                    class="group flex items-center gap-4 rounded-(--wire-radius) border border-current/15 p-4 transition hover:bg-current/5"
                >
                    <flux:icon name="document" class="size-8 shrink-0 opacity-60" />
                    <span class="min-w-0 grow">
                        <span class="block truncate font-medium">{{ data_get($media->getRelationValue('pivot'), 'metadata.caption') ?: ($media->filename ?: __('Download')) }}</span>
                        @if ($media->size)
                            <span class="block text-sm opacity-70">{{ \Illuminate\Support\Number::fileSize((int) $media->size, precision: 1) }}</span>
                        @endif
                    </span>
                    <flux:icon
                        name="arrow-down-tray"
                        class="size-5 shrink-0 opacity-60 transition group-hover:opacity-100"
                    />
                </a>
            @endif
        @endforeach
    </div>
@endif
