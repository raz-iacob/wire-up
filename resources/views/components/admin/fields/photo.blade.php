@php
    $key = $field['key'];
    $translatable = (bool) ($field['translatable'] ?? false);
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $mediaLocale = $translatable ? $locale : $defaultLocale;
@endphp

<div wire:key="field-media-{{ $key }}-{{ $mediaLocale }}">
    <livewire:admin.media-selector
        wire:model="media.{{ $key }}.{{ $mediaLocale }}"
        wire:key="field-media-selector-{{ $key }}-{{ $mediaLocale }}"
        name="field-media-{{ $key }}"
        type="image"
        :locale="$mediaLocale"
        :multi-locale="$translatable && $multiLocale"
        :multiple="false"
        :label="$label" />
    @if($help)<flux:description class="mt-1">{{ $help }}</flux:description>@endif
    <flux:error name="media.{{ $key }}.{{ $mediaLocale }}" />
</div>
