@props([
    'base',
    'menuKey',
    'index',
    'item',
    'pages' => [],
    'sidebarFields' => false,
])

@php
    $itemType = $item['type'] ?? 'page';
    $k = $item['_key'];
@endphp

<div
    wire:key="{{ $menuKey }}-{{ $k }}"
    wire:sort:item="{{ $k }}"
    class="overflow-hidden rounded-lg border border-zinc-200 dark:border-white/10"
>
  <div
    x-data="{ open: @js((bool) ($item['open'] ?? false)), title: @js(($item['label'] ?? '') !== '' ? $item['label'] : '') }"
    x-on:menu-errors-revealed.window="if ($event.detail.keys.includes(@js($k))) open = true"
  >
    <div class="flex items-center gap-2 bg-zinc-50 dark:bg-white/5 py-1.5 pl-2 pr-1.5">
        <button
            type="button"
            wire:sort:handle
            aria-label="{{ __('Drag to reorder') }}"
            class="shrink-0 cursor-grab text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
        >
            <flux:icon name="bars-3" class="size-5" />
        </button>

        <button type="button" x-on:click="open = ! open" class="min-w-0 flex-1 text-left">
            <flux:heading class="truncate text-sm!" data-test="menu-item-title" x-text="title || @js(__('New Item'))">
                {{ ($item['label'] ?? '') !== '' ? $item['label'] : __('New Item') }}
            </flux:heading>
        </button>

        <flux:button size="sm" variant="subtle" square x-on:click="open = ! open" :tooltip="__('Toggle')">
            <flux:icon name="chevron-down" variant="micro" x-show="! open" />
            <flux:icon name="chevron-up" variant="micro" x-show="open" x-cloak />
        </flux:button>

        <flux:button size="sm" variant="subtle" square icon="x-mark" :tooltip="__('Remove')" wire:click="confirmRemove('{{ $menuKey }}', {{ $index }})" />
    </div>

    <div class="grid gap-4 p-4 sm:grid-cols-2" x-show="open" x-cloak>
        <flux:field>
            <flux:label>{{ $itemType === 'heading' ? __('Heading') : __('Label') }}</flux:label>
            <flux:input wire:model="{{ $base }}.label" x-on:input="title = $event.target.value" :placeholder="$itemType === 'heading' ? __('e.g. Guides') : null" />
            <flux:error name="{{ $base }}.label" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Type') }}</flux:label>
            <flux:select wire:model.live="{{ $base }}.type">
                <flux:select.option value="page">{{ __('Page') }}</flux:select.option>
                <flux:select.option value="link">{{ __('Custom Link') }}</flux:select.option>
                @if ($sidebarFields)
                    <flux:select.option value="heading">{{ __('Group heading') }}</flux:select.option>
                @endif
            </flux:select>
            <flux:error name="{{ $base }}.type" />
        </flux:field>

        @unless ($itemType === 'heading')
            <flux:field>
                <flux:label>{{ __('Appearance') }}</flux:label>
                <flux:select wire:model="{{ $base }}.appearance">
                    <flux:select.option value="link">{{ __('Link') }}</flux:select.option>
                    <flux:select.option value="button">{{ __('Button') }}</flux:select.option>
                </flux:select>
                <flux:error name="{{ $base }}.appearance" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Target') }}</flux:label>
                <flux:select wire:model="{{ $base }}.target">
                    <flux:select.option value="_self">{{ __('Same Window') }}</flux:select.option>
                    <flux:select.option value="_blank">{{ __('New Window') }}</flux:select.option>
                </flux:select>
                <flux:error name="{{ $base }}.target" />
            </flux:field>

            @if ($itemType === 'link')
                <div class="sm:col-span-2">
                    <flux:field>
                        <flux:label>{{ __('URL') }}</flux:label>
                        <flux:input wire:model="{{ $base }}.url" type="text" placeholder="https://example.com, /about, or #contact" />
                        <flux:error name="{{ $base }}.url" />
                    </flux:field>
                </div>
            @else
                <div class="sm:col-span-2">
                    <flux:field>
                        <flux:label>{{ __('Select Page') }}</flux:label>
                        <flux:select variant="listbox" wire:model="{{ $base }}.page_id" placeholder="{{ __('Choose a page...') }}">
                            @foreach ($pages as $page)
                                <flux:select.option :value="$page['id']">{{ $page['title'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="{{ $base }}.page_id" />
                    </flux:field>
                </div>
            @endif
        @endunless

        @if ($sidebarFields && $itemType !== 'heading')
            <flux:field>
                <flux:label>{{ __('Icon') }}</flux:label>
                <flux:select variant="listbox" wire:model="{{ $base }}.icon" placeholder="{{ __('No icon') }}" clearable>
                    @foreach (config()->array('menu.icons') as $iconName)
                        <flux:select.option value="{{ $iconName }}">
                            <span class="flex items-center gap-2">
                                <flux:icon :name="$iconName" variant="micro" class="size-4" />
                                {{ $iconName }}
                            </span>
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="{{ $base }}.icon" />
            </flux:field>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>{{ __('Badge') }}</flux:label>
                    <flux:input wire:model="{{ $base }}.badge" placeholder="{{ __('e.g. New') }}" />
                    <flux:error name="{{ $base }}.badge" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Badge color') }}</flux:label>
                    <flux:select wire:model="{{ $base }}.badgeColor">
                        @foreach (config()->array('menu.badge_colors') as $color)
                            <flux:select.option value="{{ $color }}">{{ ucfirst($color) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="{{ $base }}.badgeColor" />
                </flux:field>
            </div>
        @endif
    </div>
  </div>
</div>
