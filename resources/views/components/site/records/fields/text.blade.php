@props(['record', 'field'])

@php
    $key = (string) $field['key'];
    $value = $record->fieldValue($key, (bool) ($field['translatable'] ?? false));
    $text = is_string($value) ? $value : '';
    $isRichText = ($field['type'] ?? null) === \App\Enums\FieldType::RICH_TEXT->value;
    $hasText = $isRichText ? strip_tags($text) !== '' : mb_trim($text) !== '';
    $label = $key === 'overview' ? '' : $record->recordType->fieldLabel($field);
@endphp

@if ($hasText)
    <div data-field="{{ $key }}">
        @if ($label !== '')
            <h2 class="mb-3 text-xl font-semibold tracking-tight">{{ $label }}</h2>
        @endif

        @if ($isRichText)
            <div class="[&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-4 max-w-none leading-(--wire-body-leading) *:first:mt-0 *:last:mb-0">
                {!! \App\Services\BlockHtmlSanitizer::value($text) !!}
            </div>
        @else
            <p class="leading-(--wire-body-leading) whitespace-pre-line">{{ $text }}</p>
        @endif
    </div>
@elseif ($key === 'overview' && mb_trim($record->description) !== '')
    <p class="leading-(--wire-body-leading)" data-field="overview">{{ $record->description }}</p>
@endif
