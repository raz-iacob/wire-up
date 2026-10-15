@php
    $key = $field['key'];
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $path = "data.{$key}";
    $targetTypeId = \App\Models\RecordType::query()->where('key', (string) ($field['record_type'] ?? ''))->value('id');
    $multiple = (bool) ($field['multiple'] ?? false);
@endphp

<flux:field wire:key="field-{{ $key }}-shared">
    @include('components.admin.fields.partials.label')
    @if ($targetTypeId)
        <livewire:admin.record-selector
            wire:model="{{ $path }}"
            :name="'field-records-'.$key"
            :record-type-id="$targetTypeId"
            :max="$multiple ? 30 : 1"
            :reorderable="$multiple"
            :exclude-id="$record->id"
            wire:key="field-records-{{ $key }}"
        />
    @else
        <flux:text variant="subtle">{{ __('Choose which content type this field links to under Settings → Content types.') }}</flux:text>
    @endif
    @if ($help)
        <flux:description>{{ $help }}</flux:description>
    @endif
    <flux:error name="{{ $path }}" />
    <flux:error name="{{ $path }}.*" />
</flux:field>
