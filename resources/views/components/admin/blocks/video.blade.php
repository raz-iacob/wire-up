@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks[".json_encode((string) $index)."].content";
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.intro" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" />

    <flux:radio.group wire:model.live="{{ $c }}.source" variant="segmented" label="{{ __('Video source') }}">
        <flux:radio value="upload" icon="arrow-up-tray" label="{{ __('Uploaded') }}" />
        <flux:radio value="url" icon="link" label="{{ __('Link / embed') }}" />
    </flux:radio.group>

    <div x-show="{{ $b }}?.source === 'upload'" x-cloak>
        <livewire:admin.media-selector
            wire:model="{{ $c }}.video"
            wire:key="block-{{ $block['id'] }}-video"
            name="block-{{ $block['id'] }}-video"
            type="video"
            :locale="$locale"
            :multiple="false"
            label="{{ __('Video') }}" />
    </div>

    <div x-show="{{ $b }}?.source === 'url'" x-cloak>
        <flux:input
            wire:model.lazy="{{ $c }}.url"
            type="url"
            label="{{ __('Video URL') }}"
            placeholder="https://www.youtube.com/watch?v=..."
            description="{{ __('A YouTube or Vimeo link, or a direct .mp4 / .webm URL.') }}" />
    </div>

    <livewire:admin.media-selector
        wire:model="{{ $c }}.poster"
        wire:key="block-{{ $block['id'] }}-poster"
        name="block-{{ $block['id'] }}-poster"
        type="image"
        :locale="$locale"
        :multiple="false"
        label="{{ __('Poster image') }}" />

    <flux:radio.group wire:model.live="{{ $c }}.aspect" variant="segmented" label="{{ __('Aspect ratio') }}">
        <flux:radio value="16:9" label="{{ __('16:9') }}" />
        <flux:radio value="9:16" label="{{ __('9:16') }}" />
        <flux:radio value="4:3" label="{{ __('4:3') }}" />
    </flux:radio.group>

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.live="{{ $c }}.autoplay" label="{{ __('Autoplay (muted)') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.loop" label="{{ __('Loop') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.muted" label="{{ __('Muted by default') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.controls" label="{{ __('Show controls') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
    </div>
</div>
