@php
    $key = $field['key'];
    $translatable = (bool) ($field['translatable'] ?? false);
    $required = (bool) ($field['required'] ?? false);
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $path = $translatable ? "data.{$key}.{$locale}" : "data.{$key}";
@endphp

<flux:field wire:key="field-{{ $key }}-{{ $translatable ? $locale : 'shared' }}">
    @include('components.admin.fields.partials.label')
    <flux:input type="date" wire:model.lazy="{{ $path }}" :required="$required" />
    @if($help)<flux:description>{{ $help }}</flux:description>@endif
    <flux:error name="{{ $path }}" />
</flux:field>
