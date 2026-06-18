@props(['block', 'locale', 'multiLocale' => false, 'index'])

<div class="flex flex-col gap-6">
    <x-forms.input-translated name="blocks.{{ $index }}.content.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.textarea-translated name="blocks.{{ $index }}.content.subheading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" />

    <livewire:admin.media-selector
        wire:model="blocks.{{ $index }}.content.image"
        wire:key="block-{{ $block['id'] }}-image"
        name="block-{{ $block['id'] }}-image"
        type="image"
        :locale="$locale"
        :multiple="false"
        label="{{ __('Background image') }}" />

    <flux:radio.group wire:model.lazy="blocks.{{ $index }}.content.align" label="{{ __('Text alignment') }}" variant="segmented">
        <flux:radio value="left" label="{{ __('Left') }}" />
        <flux:radio value="center" label="{{ __('Center') }}" />
        <flux:radio value="right" label="{{ __('Right') }}" />
    </flux:radio.group>
</div>
