@blaze(fold: true)

<flux:dropdown position="bottom center" class="contents">
    <flux:tooltip content="{{ __('Badge') }}" class="contents">
        <flux:editor.button icon="tag" aria-label="{{ __('Badge') }}" />
    </flux:tooltip>

    {{-- tabindex="-1" mirrors Flux's own link item: Safari focuses the nearest
         focusable parent on click, which would close the popover before our
         handlers fire unless the popover itself is focusable. --}}
    <div
        popover="manual"
        tabindex="-1"
        x-data="{
            bg: '#2563eb',
            color: '#ffffff',
            editor() { return this.$el.closest('[data-flux-editor]')?._tiptap ?? null; },
            load() {
                const attrs = this.editor()?.getAttributes('badge') ?? {};
                if (attrs.bg) this.bg = attrs.bg;
                if (attrs.color) this.color = attrs.color;
            },
            apply() {
                this.editor()?.chain().focus().setBadge({ bg: this.bg, color: this.color }).run();
            },
            remove() {
                this.editor()?.chain().focus().unsetBadge().run();
            },
        }"
        x-on:toggle="$event.newState === 'open' && load()"
        class="w-64 rounded-lg border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-600 dark:bg-zinc-700"
    >
        <div class="flex flex-col gap-4">
            <span class="wire-badge self-start" x-bind:style="`background-color:${bg};color:${color}`">{{ __('Badge') }}</span>

            <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ __('Background') }}</span>
                <input type="color" x-model="bg" aria-label="{{ __('Background colour') }}" class="h-7 w-10 cursor-pointer rounded border border-zinc-200 bg-transparent dark:border-zinc-600" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ __('Text') }}</span>
                <input type="color" x-model="color" aria-label="{{ __('Text colour') }}" class="h-7 w-10 cursor-pointer rounded border border-zinc-200 bg-transparent dark:border-zinc-600" />
            </div>

            <div class="flex items-center justify-between gap-2 pt-1">
                <button type="button" x-on:click="remove()" class="text-sm text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white">{{ __('Remove') }}</button>
                <flux:button type="button" size="sm" variant="primary" x-on:click="apply()">{{ __('Apply') }}</flux:button>
            </div>
        </div>
    </div>
</flux:dropdown>
