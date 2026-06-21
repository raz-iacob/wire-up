@props(['name' => 'title', 'locale' => 'en', 'label' => null, 'required' => false, 'multiLocale' => false, 'toolbar' => 'bold italic underline | align ~ undo redo'])

<flux:field wire:key="{{ $name }}-{{ $locale }}">
    <div class="flex items-center gap-3">
        @if($label)
        <flux:label>{{ $label }}</flux:label>
        @endif
        @if($multiLocale)
        <flux:tooltip content="{{ __('Change language') }}">
            <flux:badge size="sm" class="text-xs py-0.5!" as="button" inset="top bottom" x-on:click="$wire.dispatch('change-locale')">{{ strtoupper($locale) }}</flux:badge>
        </flux:tooltip>
        @endif
    </div>

    <div x-data="editorSource">
        <flux:editor wire:model.lazy="{{ $name }}.{{ $locale }}" :required="$required" class="**:data-[slot=content]:min-h-25!">
            <flux:editor.toolbar :items="$toolbar . ' | source'" />
            <flux:editor.content />
            <textarea
                x-ref="source"
                x-cloak
                data-editor-source
                spellcheck="false"
                x-on:change="syncSource($el)"
                style="display:none"
                rows="8"
                class="block w-full resize-y border-0 bg-transparent p-3 font-mono text-xs leading-relaxed text-zinc-700 focus:outline-none focus:ring-0 dark:text-zinc-300"
            ></textarea>
        </flux:editor>
    </div>

    <flux:error name="{{ $name }}.{{ $locale }}" />
</flux:field>
