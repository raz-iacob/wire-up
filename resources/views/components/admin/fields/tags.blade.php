@php
    $key = $field['key'];
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $path = "data.{$key}";
    $tags = is_array($data[$key] ?? null) ? $data[$key] : [];
@endphp

<flux:field wire:key="field-{{ $key }}-shared">
    @include('components.admin.fields.partials.label')
    <flux:pillbox wire:model="{{ $path }}" variant="combobox" multiple>
        <x-slot name="input">
            <flux:pillbox.input wire:model="tagSearch.{{ $key }}" :placeholder="__('Type a tag…')" />
        </x-slot>

        @foreach ($tags as $tag)
            <flux:pillbox.option :value="$tag" wire:key="field-{{ $key }}-tag-{{ $loop->index }}">
                {{ $tag }}</flux:pillbox.option>
        @endforeach

        <flux:pillbox.option.create wire:click="addTag('{{ $key }}')" min-length="1">
            {{ __('Add') }} "<span wire:text="tagSearch.{{ $key }}"></span>"
        </flux:pillbox.option.create>
    </flux:pillbox>
    @if ($help)
        <flux:description>{{ $help }}</flux:description>
    @endif
    <flux:error name="{{ $path }}" />
</flux:field>
