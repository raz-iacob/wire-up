@props([
    'c',
    'b',
    'token',
    'label',
    'locale',
    'multiLocale' => false,
])

<flux:switch wire:model.live="{{ $c }}.fields.{{ $token }}.required" label="{{ __('Required') }}" align="left" />

<div class="grid md:grid-cols-2 gap-4">
    <x-forms.input-translated name="{{ $c }}.fields.{{ $token }}.label" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Label') }}" :note="$label" />
    <x-forms.input-translated name="{{ $c }}.fields.{{ $token }}.placeholder" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Placeholder') }}" />
</div>

<div x-show="{{ $b }}?.layout === 'split'" x-cloak>
    <flux:radio.group wire:model.live="{{ $c }}.fields.{{ $token }}.column" variant="segmented" size="sm" label="{{ __('Column') }}">
        <flux:radio value="left" label="{{ __('Left') }}" />
        <flux:radio value="right" label="{{ __('Right') }}" />
    </flux:radio.group>
</div>
