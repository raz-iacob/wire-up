@php
    $key = $field['key'];
    $translatable = (bool) ($field['translatable'] ?? false);
    $required = (bool) ($field['required'] ?? false);
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $path = $translatable ? "data.{$key}.{$locale}" : "data.{$key}";
    $currency = \App\Services\SettingsService::current();
@endphp

<flux:field wire:key="field-{{ $key }}-{{ $translatable ? $locale : 'shared' }}">
    @include('components.admin.fields.partials.label')
    <flux:input.group>
        <flux:input.group.prefix>{{ $currency->currencySymbol() }}</flux:input.group.prefix>
        <flux:input mask:dynamic="$money($input, '.', ',', {{ $currency->currencyDecimals() }})" wire:model.lazy="{{ $path }}" :required="$required" inputmode="decimal" />
    </flux:input.group>
    @if($help)<flux:description>{{ $help }}</flux:description>@endif
    <flux:error name="{{ $path }}" />
</flux:field>
