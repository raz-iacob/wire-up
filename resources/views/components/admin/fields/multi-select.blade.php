@php
    $key = $field['key'];
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $path = "data.{$key}";
    $options = is_array($field['options'] ?? null) ? $field['options'] : [];
@endphp

<flux:field wire:key="field-{{ $key }}-shared">
    @include('components.admin.fields.partials.label')
    <flux:pillbox wire:model="{{ $path }}" multiple :placeholder="__('Choose options…')">
        @foreach ($options as $option)
            <flux:pillbox.option :value="$option" wire:key="field-{{ $key }}-option-{{ $loop->index }}">
                {{ $option }}</flux:pillbox.option>
        @endforeach
    </flux:pillbox>
    @if ($help)
        <flux:description>{{ $help }}</flux:description>
    @endif
    <flux:error name="{{ $path }}" />
</flux:field>
