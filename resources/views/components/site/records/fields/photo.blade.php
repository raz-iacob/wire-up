@props(['record', 'field', 'sidebar' => false])

@php
    $photo = collect($record->fieldMedia((string) $field['key'], (bool) ($field['translatable'] ?? false)))
        ->first(fn (\App\Models\Media $media): bool => $media->type === \App\Enums\MediaType::IMAGE);
@endphp

@if ($photo !== null)
    <div
        data-field="{{ $field['key'] }}"
        @class([
            'wire-card wire-card--media w-full overflow-hidden rounded-[calc(var(--wire-radius)*1.5)] bg-(--wire-card-bg)',
            'aspect-square max-w-xs md:max-w-none' => $sidebar,
        ])
    >
        <img
            src="{{ \App\Services\ImageService::mediaUrl($photo, $sidebar ? 900 : 1600) }}"
            alt="{{ $photo->alt_text ?? $record->displayHeading() }}"
            loading="lazy"
            @class(['w-full', 'h-full object-cover' => $sidebar, 'h-auto' => ! $sidebar])
        />
    </div>
@endif
