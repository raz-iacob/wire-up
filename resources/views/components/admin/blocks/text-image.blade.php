@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks['".addslashes((string) $index)."'].content";
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated
        name="{{ $c }}.heading"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Heading') }}"
    />
    <x-admin.blocks.partials.heading-level :name="$c" />
    <x-forms.texteditor-translated
        name="{{ $c }}.body"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Text') }}"
    />

    <livewire:admin.media-selector
        wire:model="{{ $c }}.image"
        wire:key="block-{{ $block['id'] }}-image"
        name="block-{{ $block['id'] }}-image"
        type="image"
        :locale="$locale"
        :multiple="false"
        label="{{ __('Image') }}"
    />

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
        <flux:switch
            wire:model.lazy="{{ $c }}.reverseLayout"
            label="{{ __('Display image on the right') }}"
            align="left"
        />
    </div>

    <div x-show="{{ $b }}?.hasBackground" x-cloak class="grid gap-4 md:grid-cols-2">
        <flux:color-picker
            wire:model="{{ $c }}.bg"
            clearable
            label="{{ __('Background color') }}"
            placeholder="{{ __('Theme') }}"
        />
        <flux:color-picker
            wire:model="{{ $c }}.textColor"
            clearable
            label="{{ __('Text color') }}"
            placeholder="{{ __('Theme') }}"
        />
    </div>

    <flux:radio.group wire:model.lazy="{{ $c }}.columnSplit" variant="segmented" label="{{ __('Column split') }}">
        <flux:radio value="even" label="{{ __('Even') }}" />
        <flux:radio value="text-wide" label="{{ __('Wider text') }}" />
        <flux:radio value="image-wide" label="{{ __('Wider image') }}" />
    </flux:radio.group>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:select wire:model.lazy="{{ $c }}.imageRatio" variant="listbox" label="{{ __('Image shape') }}">
            <flux:select.option value="auto">{{ __('Its own') }}</flux:select.option>
            <flux:select.option value="1:1">{{ __('Square') }}</flux:select.option>
            <flux:select.option value="4:3">4:3</flux:select.option>
            <flux:select.option value="3:2">3:2</flux:select.option>
            <flux:select.option value="16:9">16:9</flux:select.option>
            <flux:select.option value="3:4">{{ __('Portrait') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.lazy="{{ $c }}.imageRadius" variant="listbox" label="{{ __('Image corners') }}">
            <flux:select.option value="default">{{ __('Default') }}</flux:select.option>
            <flux:select.option value="none">{{ __('Square') }}</flux:select.option>
            <flux:select.option value="small">{{ __('Small') }}</flux:select.option>
            <flux:select.option value="large">{{ __('Large') }}</flux:select.option>
            <flux:select.option value="full">{{ __('Round') }}</flux:select.option>
        </flux:select>
    </div>

    @foreach (['ctaPrimary' => __('Primary button'), 'ctaSecondary' => __('Secondary button')] as $cta => $ctaLabel)
        <flux:switch
            wire:model.live="{{ $c }}.{{ $cta }}.enabled"
            label="{{ __('Show :button', ['button' => strtolower($ctaLabel)]) }}"
            align="left"
        />

        <div x-show="{{ $b }}?.{{ $cta }}?.enabled" class="grid gap-4 md:grid-cols-2">
            <x-forms.input-translated
                name="{{ $c }}.{{ $cta }}.text"
                :locale="$locale"
                :multi-locale="$multiLocale"
                label="{{ __('Button text') }}"
            />

            <flux:select wire:model.live="{{ $c }}.{{ $cta }}.link.type" variant="listbox" label="{{ __('Link to') }}">
                <flux:select.option value="page">{{ __('A page') }}</flux:select.option>
                <flux:select.option value="url">{{ __('External URL') }}</flux:select.option>
                <flux:select.option value="anchor">{{ __('Section on this page') }}</flux:select.option>
            </flux:select>

            @php($linkType = data_get($block, "content.{$cta}.link.type", 'url'))
            <div class="col-span-2">
                @if ($linkType === 'page')
                    <flux:select
                        wire:model="{{ $c }}.{{ $cta }}.link.value"
                        variant="listbox"
                        searchable
                        placeholder="{{ __('Choose a page') }}"
                        label="{{ __('Page') }}"
                    >
                        @foreach ($pageOptions as $pageId => $pageTitle)
                            <flux:select.option value="{{ $pageId }}">{{ $pageTitle }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif ($linkType === 'anchor')
                    <flux:input
                        wire:model.lazy="{{ $c }}.{{ $cta }}.link.value"
                        label="{{ __('Section anchor') }}"
                        placeholder="#contact"
                    />
                @else
                    <div class="flex flex-col gap-3">
                        <x-forms.url-input wire:model.lazy="{{ $c }}.{{ $cta }}.link.value" label="{{ __('URL') }}" />
                        <flux:switch
                            wire:model.lazy="{{ $c }}.{{ $cta }}.link.newTab"
                            label="{{ __('Open in a new tab') }}"
                            align="left"
                        />
                    </div>
                @endif
            </div>

            <flux:color-picker
                wire:model="{{ $c }}.{{ $cta }}.bg"
                clearable
                label="{{ __('Button color') }}"
                placeholder="{{ __('Theme') }}"
            />
            <flux:color-picker
                wire:model="{{ $c }}.{{ $cta }}.textColor"
                clearable
                label="{{ __('Text color') }}"
                placeholder="{{ __('Theme') }}"
            />
        </div>
    @endforeach
</div>
