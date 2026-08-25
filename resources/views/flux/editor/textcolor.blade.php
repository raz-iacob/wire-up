@blaze(fold: true)

@php
    $tokens = [
        'accent' => ['label' => __('Accent'), 'var' => 'var(--wire-accent)'],
        'muted' => ['label' => __('Muted'), 'var' => 'var(--wire-muted)'],
        'text' => ['label' => __('Default'), 'var' => 'var(--wire-body-text)'],
    ];
@endphp

<flux:dropdown position="bottom center" class="contents">
    <flux:tooltip content="{{ __('Text colour') }}" class="contents">
        <flux:editor.button icon="paint-brush" aria-label="{{ __('Text colour') }}" />
    </flux:tooltip>

    {{-- tabindex="-1" mirrors the badge item: Safari focuses the nearest focusable
         parent on click, closing the popover before the handlers fire. --}}
    <div
        popover="manual"
        tabindex="-1"
        x-data="{
            custom: '#2563eb',
            editor() {
                return this.$el.closest('[data-flux-editor]')?._tiptap ?? null;
            },
            load() {
                const attrs = this.editor()?.getAttributes('textColor') ?? {};
                if (attrs.color) this.custom = attrs.color;
            },
            token(name) {
                this.editor()?.chain().focus().setTextColor({ token: name }).run();
            },
            apply() {
                this.editor()?.chain().focus().setTextColor({ color: this.custom }).run();
            },
            remove() {
                this.editor()?.chain().focus().unsetTextColor().run();
            },
        }"
        x-on:toggle="$event.newState === 'open' && load()"
        class="w-56 rounded-lg border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-600 dark:bg-zinc-700"
    >
        <div class="flex flex-col gap-3">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400"> {{ __('Theme colours') }} </span>

            @foreach ($tokens as $key => $token)
                <button
                    type="button"
                    x-on:click="token('{{ $key }}')"
                    class="flex items-center gap-3 rounded-md px-2 py-1.5 text-start text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-600"
                >
                    <span
                        class="size-4 shrink-0 rounded-full ring-1 ring-black/20 dark:ring-white/25"
                        style="background-color: {{ $token['var'] }}"
                    ></span>
                    {{ $token['label'] }}
                </button>
            @endforeach

            <flux:separator variant="subtle" />

            <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ __('Custom') }}</span>
                <div class="flex items-center gap-2">
                    <input
                        type="color"
                        x-model="custom"
                        aria-label="{{ __('Custom colour') }}"
                        class="h-7 w-10 cursor-pointer rounded border border-zinc-200 bg-transparent dark:border-zinc-600"
                    />
                    <flux:button type="button" size="sm" x-on:click="apply()">{{ __('Use') }}</flux:button>
                </div>
            </div>

            <div class="pt-1">
                <button
                    type="button"
                    x-on:click="remove()"
                    class="text-sm text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                >
                    {{ __('Remove colour') }}
                </button>
            </div>
        </div>
    </div>
</flux:dropdown>
