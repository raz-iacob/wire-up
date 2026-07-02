@php
    $key = $field['key'];
    $translatable = (bool) ($field['translatable'] ?? false);
    $required = (bool) ($field['required'] ?? false);
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $path = $translatable ? "data.{$key}.{$locale}" : "data.{$key}";
    $options = is_array($field['options'] ?? null) ? $field['options'] : [];
@endphp

<flux:field wire:key="field-{{ $key }}-{{ $translatable ? $locale : 'shared' }}">
    @include('components.admin.fields.partials.label')
    <flux:select variant="listbox" wire:model="{{ $path }}" :required="$required" :placeholder="__('Choose an option')" clearable>
        @foreach($options as $option)
            <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
        @endforeach
    </flux:select>
    @if($help)<flux:description>{{ $help }}</flux:description>@endif
    <flux:error name="{{ $path }}" />
</flux:field>
