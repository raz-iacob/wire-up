@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks[".json_encode((string) $index)."].content";
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.body" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Text') }}" />

    <livewire:admin.media-selector
        wire:model="{{ $c }}.image"
        wire:key="block-{{ $block['id'] }}-image"
        name="block-{{ $block['id'] }}-image"
        type="image"
        :locale="$locale"
        :multiple="false"
        label="{{ __('Image') }}" />

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
        <flux:switch wire:model.lazy="{{ $c }}.reverseLayout" label="{{ __('Display image on the right') }}" align="left" />
    </div>

    @foreach (['ctaPrimary' => __('Primary button'), 'ctaSecondary' => __('Secondary button')] as $cta => $ctaLabel)
        <flux:switch wire:model.live="{{ $c }}.{{ $cta }}.enabled" label="{{ __('Show :button', ['button' => strtolower($ctaLabel)]) }}" align="left" />

        <div x-show="{{ $b }}?.{{ $cta }}?.enabled" class="grid md:grid-cols-2 gap-4">
            <x-forms.input-translated name="{{ $c }}.{{ $cta }}.text" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Button text') }}" />

            <flux:select wire:model.live="{{ $c }}.{{ $cta }}.link.type" variant="listbox" label="{{ __('Link to') }}">
                <flux:select.option value="page">{{ __('A page') }}</flux:select.option>
                <flux:select.option value="url">{{ __('External URL') }}</flux:select.option>
                <flux:select.option value="anchor">{{ __('Section on this page') }}</flux:select.option>
            </flux:select>

            @php($linkType = data_get($block, "content.{$cta}.link.type", 'url'))
            <div class="col-span-2">
                @if ($linkType === 'page')
                    <flux:select wire:model="{{ $c }}.{{ $cta }}.link.value" variant="listbox" searchable placeholder="{{ __('Choose a page') }}" label="{{ __('Page') }}">
                        @foreach ($pageOptions as $pageId => $pageTitle)
                            <flux:select.option value="{{ $pageId }}">{{ $pageTitle }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif ($linkType === 'anchor')
                    <flux:input wire:model.lazy="{{ $c }}.{{ $cta }}.link.value" label="{{ __('Section anchor') }}" placeholder="#contact" />
                @else
                    <div class="flex flex-col gap-3">
                        <flux:input wire:model.lazy="{{ $c }}.{{ $cta }}.link.value" type="url" label="{{ __('URL') }}" placeholder="https://example.com" />
                        <flux:switch wire:model.lazy="{{ $c }}.{{ $cta }}.link.newTab" label="{{ __('Open in a new tab') }}" align="left" />
                    </div>
                @endif
            </div>

            <flux:color-picker wire:model="{{ $c }}.{{ $cta }}.bg" clearable label="{{ __('Button color') }}" placeholder="{{ __('Theme') }}" />
            <flux:color-picker wire:model="{{ $c }}.{{ $cta }}.textColor" clearable label="{{ __('Text color') }}" placeholder="{{ __('Theme') }}" />
        </div>
    @endforeach
</div>
