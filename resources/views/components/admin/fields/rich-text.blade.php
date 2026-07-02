@php
    $key = $field['key'];
    $translatable = (bool) ($field['translatable'] ?? false);
    $required = (bool) ($field['required'] ?? false);
    $help = (string) ($field['help'] ?? '');
    $label = $field['label'][$locale] ?? \Illuminate\Support\Arr::first($field['label']) ?? $key;
    $path = $translatable ? "data.{$key}.{$locale}" : "data.{$key}";
@endphp

<flux:field wire:key="field-{{ $key }}-{{ $translatable ? $locale : 'shared' }}">
    @include('components.admin.fields.partials.label')

    <div x-data="editorSource">
        <flux:editor wire:model.lazy="{{ $path }}" :required="$required" class="**:data-[slot=content]:min-h-25!">
            <flux:editor.toolbar items="bold italic underline | bullet ordered | align | link ~ undo redo | badge | source" />
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

    @if($help)<flux:description>{{ $help }}</flux:description>@endif
    <flux:error name="{{ $path }}" />
</flux:field>
