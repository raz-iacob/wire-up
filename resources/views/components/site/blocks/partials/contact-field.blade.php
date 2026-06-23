@props([
    'item',
    'inputClass' => '',
    'layout' => 'stacked',
])

@php($field = $item['field'])
@php($hasLabel = $field['label'] !== '')
@php($errorClass = 'mt-1 inline-block px-2 py-0.5 text-sm font-medium rounded-(--wire-radius) bg-red-100 text-red-700')

@if ($item['kind'] === 'builtin')
    <div @class(['flex flex-col gap-1.5', 'md:col-span-2' => $layout === 'full' && $field['type'] === 'textarea']) wire:key="builtin-{{ $field['key'] }}">
        @if ($hasLabel)
            <label for="cf-{{ $field['key'] }}" class="text-sm font-medium">{{ $field['label'] }}@if ($field['required'])<span aria-hidden="true"> *</span>@endif</label>
        @endif
        @if ($field['type'] === 'textarea')
            <textarea id="cf-{{ $field['key'] }}" wire:model="{{ $field['key'] }}" rows="5" class="{{ $inputClass }}" @if ($field['placeholder'] !== '') placeholder="{{ $field['placeholder'] }}" @endif @unless ($hasLabel) aria-label="{{ $field['aria'] }}" @endunless></textarea>
        @else
            <input id="cf-{{ $field['key'] }}" type="{{ $field['type'] }}" wire:model="{{ $field['key'] }}" class="{{ $inputClass }}" @if ($field['placeholder'] !== '') placeholder="{{ $field['placeholder'] }}" @endif @unless ($hasLabel) aria-label="{{ $field['aria'] }}" @endunless />
        @endif
        @error($field['key'])<span class="{{ $errorClass }}">{{ $message }}</span>@enderror
    </div>
@else
    @php($path = 'custom.'.$field['id'])
    <div @class(['flex flex-col gap-1.5', 'md:col-span-2' => $layout === 'full' && in_array($field['type'], ['textarea', 'checkbox'], true)]) wire:key="custom-{{ $field['id'] }}">
        @if ($field['type'] === 'checkbox')
            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" wire:model="{{ $path }}" @unless ($hasLabel) aria-label="{{ $field['aria'] }}" @endunless />
                @if ($hasLabel)<span>{{ $field['label'] }}@if ($field['required'])<span aria-hidden="true"> *</span>@endif</span>@endif
            </label>
        @else
            @if ($hasLabel)
                <label for="cf-{{ $field['id'] }}" class="text-sm font-medium">{{ $field['label'] }}@if ($field['required'])<span aria-hidden="true"> *</span>@endif</label>
            @endif
            @switch($field['type'])
                @case('textarea')
                    <textarea id="cf-{{ $field['id'] }}" wire:model="{{ $path }}" rows="5" class="{{ $inputClass }}" @unless ($hasLabel) aria-label="{{ $field['aria'] }}" @endunless></textarea>
                    @break
                @case('select')
                    <select id="cf-{{ $field['id'] }}" wire:model="{{ $path }}" class="{{ $inputClass }}" @unless ($hasLabel) aria-label="{{ $field['aria'] }}" @endunless>
                        <option value="">{{ __('Please choose…') }}</option>
                        @foreach ($field['options'] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @break
                @default
                    <input id="cf-{{ $field['id'] }}" type="{{ $field['type'] }}" wire:model="{{ $path }}" class="{{ $inputClass }}" @unless ($hasLabel) aria-label="{{ $field['aria'] }}" @endunless />
            @endswitch
        @endif
        @error($path)<span class="{{ $errorClass }}">{{ $message }}</span>@enderror
    </div>
@endif
