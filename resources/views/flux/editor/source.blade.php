@blaze(fold: true)

<flux:tooltip content="{{ __('View source') }}" class="contents">
    <flux:editor.button icon="code-bracket" x-on:click="toggleSource($el)" x-bind:data-match="source" aria-label="{{ __('View source') }}" />
</flux:tooltip>
