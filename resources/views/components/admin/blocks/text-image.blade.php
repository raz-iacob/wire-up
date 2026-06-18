@props(['block', 'locale', 'multiLocale' => false, 'index'])

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="blocks.{{ $index }}.content.body" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Text') }}" />

    <livewire:admin.media-selector
        wire:model="blocks.{{ $index }}.content.image"
        wire:key="block-{{ $block['id'] }}-image"
        name="block-{{ $block['id'] }}-image"
        type="image"
        :locale="$locale"
        :multiple="false"
        label="{{ __('Image') }}" />

    <flux:switch wire:model.lazy="blocks.{{ $index }}.content.reverseLayout" label="{{ __('Display image on the right') }}" align="left" />
</div>
