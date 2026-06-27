@props(['block', 'locale', 'multiLocale' => false, 'index'])

@php
    $c = "blocks.{$index}.content";
    $items = data_get($block, 'content.items', []);

    $socialFields = [
        'email' => ['label' => __('Email'), 'placeholder' => 'name@example.com'],
        'website' => ['label' => __('Website'), 'placeholder' => 'https://example.com'],
        'linkedin' => ['label' => __('LinkedIn'), 'placeholder' => 'https://linkedin.com/in/username'],
        'x' => ['label' => __('X (Twitter)'), 'placeholder' => 'https://x.com/username'],
        'instagram' => ['label' => __('Instagram'), 'placeholder' => 'https://instagram.com/username'],
    ];
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.intro" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" />

    <div class="flex flex-col gap-2">
        <flux:label>{{ __('Members') }}</flux:label>

        <div wire:sort="reorderTeamItems" wire:sort:group="team-{{ $block['id'] }}" class="flex flex-col gap-3">
            @foreach ($items as $i => $item)
                @php($memberName = \Illuminate\Support\Str::of((string) data_get($item, "name.{$locale}"))->squish()->limit(50)->value())
                <flux:card
                    size="sm"
                    class="p-0! overflow-hidden"
                    wire:key="team-item-{{ $item['id'] ?? $i }}"
                    wire:sort:item="{{ $item['id'] ?? $i }}"
                    x-data="{ open: false }"
                    x-on:open-block-item.window="$event.detail.id === '{{ $item['id'] ?? $i }}' && (open = true)"
                >
                    <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                            <flux:icon name="bars-3" variant="mini" />
                        </div>

                        <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                            <flux:heading size="sm" class="truncate">{{ $memberName !== '' ? $memberName : __('Member :number', ['number' => $i + 1]) }}</flux:heading>
                        </button>

                        <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                            <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                            </flux:button>
                            <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removeTeamItem('{{ $block['id'] }}', {{ $i }})" :tooltip="__('Remove member')" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 p-4" x-show="open" x-collapse x-cloak>
                        <livewire:admin.blocks.item-media
                            :block-id="$block['id']"
                            item-id="{{ $item['id'] ?? $i }}"
                            field="photo"
                            :value="data_get($item, 'photo')"
                            media-type="image"
                            :multiple="false"
                            :crops="['default' => ['label' => __('Square'), 'w' => 400, 'h' => 400]]"
                            :locale="$locale"
                            :multi-locale="$multiLocale"
                            label="{{ __('Photo') }}"
                            wire:key="team-photo-{{ $block['id'] }}-{{ $item['id'] ?? $i }}" />

                        <div class="grid md:grid-cols-2 gap-4">
                            <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.name" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Name') }}" />
                            <x-forms.input-translated name="{{ $c }}.items.{{ $i }}.role" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Role') }}" />
                        </div>

                        <x-forms.texteditor-translated name="{{ $c }}.items.{{ $i }}.bio" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Bio') }}" />

                        <div class="flex flex-col gap-3">
                            <flux:label>{{ __('Social links') }}</flux:label>
                            <div class="grid md:grid-cols-2 gap-4">
                                @foreach ($socialFields as $socialKey => $socialMeta)
                                    <flux:input
                                        wire:model.lazy="{{ $c }}.items.{{ $i }}.socials.{{ $socialKey }}"
                                        label="{{ $socialMeta['label'] }}"
                                        placeholder="{{ $socialMeta['placeholder'] }}" />
                                @endforeach
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div>
            <flux:button size="sm" icon="plus" wire:click="addTeamItem('{{ $block['id'] }}')">{{ __('Add member') }}</flux:button>
        </div>
    </div>

    <flux:radio.group wire:model.live="{{ $c }}.layout" variant="segmented" label="{{ __('Layout') }}">
        <flux:radio value="circle" label="{{ __('Circle') }}" />
        <flux:radio value="card" label="{{ __('Card') }}" />
        <flux:radio value="overlay" label="{{ __('Overlay') }}" />
        <flux:radio value="portrait" label="{{ __('Portrait') }}" />
    </flux:radio.group>

    <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
        @foreach ([2, 3, 4] as $columnOption)
            <flux:radio value="{{ $columnOption }}" label="{{ $columnOption }}" />
        @endforeach
    </flux:radio.group>

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
