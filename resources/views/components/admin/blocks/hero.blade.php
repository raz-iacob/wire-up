@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks[".json_encode((string) $index)."].content";
    $settings = \App\Services\SettingsService::current();
    $headerBg = $settings->color('header_bg') ?? '#ffffff';
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" toolbar="bold italic underline" />
    <x-forms.texteditor-translated name="{{ $c }}.subheading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" toolbar="bold italic underline | link ~ undo redo" />

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:color-picker wire:model="{{ $c }}.headingColor" clearable label="{{ __('Heading color') }}" placeholder="{{ __('Header text') }}" />
        <flux:color-picker wire:model="{{ $c }}.subheadingColor" clearable label="{{ __('Subheading color') }}" placeholder="{{ __('Header text') }}" />
    </div>

    <flux:radio.group wire:model.live="{{ $c }}.background.type" label="{{ __('Background') }}" variant="segmented">
        <flux:radio value="image" label="{{ __('Image') }}" icon="photo" />
        <flux:radio value="color" label="{{ __('Color') }}" icon="swatch" />
    </flux:radio.group>

    <div x-show="{{ $b }}?.background?.type === 'image'">
        <livewire:admin.media-selector
            wire:model="{{ $c }}.background.image"
            wire:key="block-{{ $block['id'] }}-image"
            name="block-{{ $block['id'] }}-image"
            type="image"
            :crops="['default' => ['label' => __('Background image'), 'q' => 80, 'fm' => 'jpg']]"
            label="{{ __('Background image') }}" />
    </div>

    <div x-show="{{ $b }}?.background?.type === 'color'" class="flex flex-col gap-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:color-picker wire:model="{{ $c }}.background.gradient.start" :value="$headerBg" clearable label="{{ __('Start color') }}" placeholder="{{ $headerBg }}" />
            <flux:color-picker wire:model="{{ $c }}.background.gradient.end" :value="$headerBg" clearable label="{{ __('End color') }}" placeholder="{{ $headerBg }}" />
        </div>
        <flux:radio.group wire:model.lazy="{{ $c }}.background.gradient.direction" label="{{ __('Gradient direction') }}" variant="segmented">
            <flux:radio value="to-r" label="{{ __('Left to right') }}" icon="arrow-right" />
            <flux:radio value="to-b" label="{{ __('Top to bottom') }}" icon="arrow-down" />
        </flux:radio.group>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:radio.group wire:model.lazy="{{ $c }}.align" label="{{ __('Text alignment') }}" variant="segmented">
            <flux:radio value="left" icon="bars-3-bottom-left" label="{{ __('Left') }}" />
            <flux:radio value="center" icon="bars-3" label="{{ __('Center') }}" />
            <flux:radio value="right" icon="bars-3-bottom-right" label="{{ __('Right') }}" />
        </flux:radio.group>

        <flux:radio.group wire:model.lazy="{{ $c }}.verticalAlign" label="{{ __('Vertical alignment') }}" variant="segmented">
            <flux:radio value="top" icon="arrow-up" label="{{ __('Top') }}" />
            <flux:radio value="center" icon="minus" label="{{ __('Center') }}" />
            <flux:radio value="bottom" icon="arrow-down" label="{{ __('Bottom') }}" />
        </flux:radio.group>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:select wire:model.lazy="{{ $c }}.width" variant="listbox" label="{{ __('Width') }}">
            <flux:select.option value="full">{{ __('Full width') }}</flux:select.option>
            <flux:select.option value="container">{{ __('Container') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.lazy="{{ $c }}.height" variant="listbox" label="{{ __('Height') }}">
            <flux:select.option value="auto">{{ __('Fit content') }}</flux:select.option>
            <flux:select.option value="large">{{ __('Large') }}</flux:select.option>
            <flux:select.option value="screen">{{ __('Full screen') }}</flux:select.option>
        </flux:select>
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
