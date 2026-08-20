@props([
    'locale',
    'menuOptions' => [],
    'layout' => [],
    'name' => 'page',
    'description' => null,
    'cssDescription' => null,
])

@php
    $description ??= __('Optional design overrides for this page.');
    $cssDescription ??= __('Add custom CSS rules that apply only to this page.');
@endphp

<flux:fieldset class="pb-6">
    <flux:legend>{{ __('Layout') }}</flux:legend>
    <flux:description>{{ $description }}</flux:description>

    <div class="mt-6 flex flex-col gap-6">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:switch wire:model.live="layout.hideHeader" label="{{ __('Hide site header') }}" align="left" />
            <flux:switch wire:model.live="layout.hideFooter" label="{{ __('Hide site footer') }}" align="left" />
        </div>

        <livewire:admin.media-selector
            wire:model="layout.backgroundImage"
            wire:key="{{ $name }}-background-image"
            name="{{ $name }}-background-image"
            type="image"
            :locale="$locale"
            :multiple="false"
            label="{{ __('Background image') }}"
        />

        <div class="grid gap-4 md:grid-cols-2">
            <flux:color-picker
                wire:model="layout.backgroundColor"
                clearable
                label="{{ __('Background color') }}"
                placeholder="{{ __('Theme') }}"
            />
            <div class="flex md:h-full md:items-center md:pt-5">
                <flux:switch
                    wire:model.live="layout.backgroundFixed"
                    label="{{ __('Fixed background') }}"
                    align="left"
                />
            </div>
        </div>

        <flux:separator variant="subtle" />

        <flux:field>
            <flux:label>{{ __('Menus to show') }}</flux:label>

            <flux:pillbox
                variant="combobox"
                multiple
                wire:model="layout.sidebar.menus"
                placeholder="{{ __('Select menus…') }}"
            >
                @forelse ($menuOptions as $option)
                    <flux:pillbox.option value="{{ $option['key'] }}" wire:key="sidebar-menu-{{ $option['key'] }}">
                        {{ $option['label'] }}</flux:pillbox.option>
                @empty
                    <flux:pillbox.option.empty>{{ __('No menus yet.') }}</flux:pillbox.option.empty>
                @endforelse
            </flux:pillbox>

            <flux:description>
                {{ __('Create and edit menus in') }}
                <flux:link
                    href="{{ route('admin.settings-menus') }}"
                    wire:navigate
                    >{{ __('Settings → Menus') }}</flux:link
                >.
            </flux:description>
        </flux:field>

        <div>
            <flux:modal.trigger name="{{ $name }}-custom-css">
                <flux:button
                    icon="code-bracket"
                    variant="filled"
                >{{ ($layout['customCss'] ?? '') !== '' ? __('Edit custom CSS') : __('Add custom CSS') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:modal name="{{ $name }}-custom-css" class="w-full md:max-w-2xl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Custom CSS') }}</flux:heading>
                    <flux:text class="mt-2">{{ $cssDescription }}</flux:text>
                </div>
                <flux:textarea
                    wire:model.lazy="layout.customCss"
                    rows="12"
                    class="font-mono text-sm"
                    placeholder=".my-class &#123; color: red; &#125;"
                />
                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="primary">{{ __('Done') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    </div>
</flux:fieldset>
