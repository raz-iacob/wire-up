@props(['record', 'field', 'purchasable' => false, 'sidebar' => false])

@php
    $fieldType = \App\Enums\FieldType::tryFrom((string) ($field['type'] ?? '')) ?? \App\Enums\FieldType::TEXT;
    $key = (string) ($field['key'] ?? '');
    $isPrice = $key === \App\Services\RecordTypePresets::PRICE_FIELD;
@endphp

@if ($key === 'heading')
    <h1 class="text-(length:--wire-heading-size) tracking-tight" data-field="heading">
        {{ $record->displayHeading() }}
    </h1>
@elseif ($isPrice)
    <x-site.records.fields.price :record="$record" :purchasable="$purchasable" />
@elseif (in_array($fieldType, [\App\Enums\FieldType::RICH_TEXT, \App\Enums\FieldType::TEXTAREA], true))
    <x-site.records.fields.text :record="$record" :field="$field" />
@elseif ($fieldType === \App\Enums\FieldType::PHOTO)
    <x-site.records.fields.photo :record="$record" :field="$field" :sidebar="$sidebar" />
@elseif ($fieldType === \App\Enums\FieldType::MEDIA_GALLERY)
    <x-site.records.fields.gallery :record="$record" :field="$field" />
@elseif (in_array($fieldType, [\App\Enums\FieldType::VIDEO, \App\Enums\FieldType::AUDIO, \App\Enums\FieldType::DOCUMENT], true))
    <x-site.records.fields.media :record="$record" :field="$field" />
@elseif ($fieldType === \App\Enums\FieldType::RELATIONSHIP)
    <x-site.records.fields.links :record="$record" :field="$field" />
@else
    <x-site.records.fields.value :record="$record" :field="$field" />
@endif
