@php
    $key = $field['key'];
    $translatable = (bool) ($field['translatable'] ?? false);
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $path = $translatable ? "data.{$key}.{$locale}" : "data.{$key}";
@endphp

<flux:field wire:key="field-{{ $key }}-{{ $translatable ? $locale : 'shared' }}">
    <flux:switch wire:model="{{ $path }}" :label="$label" :description="$help !== '' ? $help : null" align="left" />
    <flux:error name="{{ $path }}" />
</flux:field>
