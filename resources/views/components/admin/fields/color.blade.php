@php
    $key = $field['key'];
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $path = "data.{$key}";
@endphp

<flux:field wire:key="field-{{ $key }}-shared">
    @include('components.admin.fields.partials.label')
    <flux:color-picker wire:model="{{ $path }}" clearable />
    @if ($help)
        <flux:description>{{ $help }}</flux:description>
    @endif
    <flux:error name="{{ $path }}" />
</flux:field>
