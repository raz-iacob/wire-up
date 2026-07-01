@props([
    'type',
    'index',
])

<div
    wire:key="rtype-{{ $type['_key'] }}"
    wire:sort:item="{{ $type['_key'] }}"
    class="overflow-hidden rounded-lg border border-zinc-200 dark:border-white/10"
>
    <div
        x-data="{ open: @js((bool) ($type['open'] ?? false)), title: @js($type['name']) }"
        x-on:record-type-errors.window="if ($event.detail.keys.includes(@js($type['_key']))) open = true"
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

            <flux:icon :name="$type['icon'] !== '' ? $type['icon'] : 'rectangle-stack'" class="size-4 shrink-0 text-zinc-400" />

            <button type="button" x-on:click="open = ! open" class="min-w-0 flex-1 text-left">
                <flux:heading class="truncate text-sm!" x-text="title || @js(__('Untitled type'))">
                    {{ $type['name'] !== '' ? $type['name'] : __('Untitled type') }}
                </flux:heading>
            </button>

            <flux:button size="sm" variant="subtle" square x-on:click="open = ! open" :tooltip="__('Toggle')">
                <flux:icon name="chevron-down" variant="micro" x-show="! open" />
                <flux:icon name="chevron-up" variant="micro" x-show="open" x-cloak />
            </flux:button>

            <flux:button size="sm" variant="subtle" square icon="x-mark" :tooltip="__('Remove')" wire:click="confirmRemove('{{ $type['_key'] }}')" />
        </div>

        <div class="space-y-6 p-4" x-show="open" x-cloak>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="types.{{ $index }}.name" x-on:input="title = $event.target.value" :placeholder="__('Products')" />
                    <flux:error name="types.{{ $index }}.name" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('URL prefix') }}</flux:label>
                    <flux:input wire:model="types.{{ $index }}.slug_prefix" :placeholder="__('e.g. /products/my-item')" />
                    <flux:error name="types.{{ $index }}.slug_prefix" />
                </flux:field>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <flux:label>{{ __('Fields') }}</flux:label>
                    <flux:dropdown>
                        <flux:button type="button" size="sm" icon="plus" icon-trailing="chevron-down">{{ __('Add field') }}</flux:button>
                        <flux:menu>
                            @foreach (\App\Enums\FieldType::cases() as $fieldType)
                                <flux:menu.item :icon="$fieldType->icon()" wire:click="addField('{{ $type['_key'] }}', '{{ $fieldType->value }}')">{{ $fieldType->label() }}</flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                </div>

                @if ($type['fields'] === [])
                    <flux:text variant="subtle">{{ __('No custom fields. Records still have a title, description, slug and SEO.') }}</flux:text>
                @else
                    <div wire:sort="reorderFields" class="space-y-2">
                        @foreach ($type['fields'] as $fieldIndex => $field)
                            <div
                                wire:sort:item="{{ $field['_key'] }}"
                                wire:key="field-{{ $field['_key'] }}"
                                class="overflow-hidden rounded-lg border border-zinc-200 dark:border-white/10"
                            >
                                <div
                                    x-data="{ open: @js((bool) ($field['open'] ?? false)), title: @js($field['label']) }"
                                    x-on:record-type-errors.window="if ($event.detail.keys.includes(@js($field['_key']))) open = true"
                                >
                                    <div class="flex items-center gap-2 bg-zinc-50 dark:bg-white/5 py-1.5 pl-2 pr-1.5">
                                        <button type="button" wire:sort:handle aria-label="{{ __('Drag to reorder') }}" class="shrink-0 cursor-grab text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                                            <flux:icon name="bars-3" class="size-5" />
                                        </button>

                                        <button type="button" x-on:click="open = ! open" class="min-w-0 flex-1 text-left">
                                            <flux:heading class="truncate text-sm!" x-text="title || @js(__('New field'))">
                                                {{ $field['label'] !== '' ? $field['label'] : __('New field') }}
                                            </flux:heading>
                                        </button>

                                        <flux:button size="sm" variant="subtle" square x-on:click="open = ! open" :tooltip="__('Toggle')">
                                            <flux:icon name="chevron-down" variant="micro" x-show="! open" />
                                            <flux:icon name="chevron-up" variant="micro" x-show="open" x-cloak />
                                        </flux:button>

                                        <flux:button size="sm" variant="subtle" square icon="x-mark" :tooltip="__('Remove field')" wire:click="removeField('{{ $field['_key'] }}')" />
                                    </div>

                                    <div class="grid gap-3 p-4 sm:grid-cols-2" x-show="open" x-cloak>
                                        <flux:field>
                                            <flux:label>{{ __('Label') }}</flux:label>
                                            <flux:input wire:model="types.{{ $index }}.fields.{{ $fieldIndex }}.label" x-on:input="title = $event.target.value" />
                                            <flux:error name="types.{{ $index }}.fields.{{ $fieldIndex }}.label" />
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>{{ __('Key') }}</flux:label>
                                            <flux:input wire:model="types.{{ $index }}.fields.{{ $fieldIndex }}.key" />
                                            <flux:error name="types.{{ $index }}.fields.{{ $fieldIndex }}.key" />
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>{{ __('Type') }}</flux:label>
                                            <flux:select wire:model.live="types.{{ $index }}.fields.{{ $fieldIndex }}.type">
                                                @foreach (\App\Enums\FieldType::cases() as $fieldType)
                                                    <flux:select.option :value="$fieldType->value">{{ $fieldType->label() }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>{{ __('Help text') }}</flux:label>
                                            <flux:input wire:model="types.{{ $index }}.fields.{{ $fieldIndex }}.help" />
                                        </flux:field>
                                        @if (\App\Enums\FieldType::tryFrom($field['type'])?->supportsOptions())
                                            <flux:textarea wire:model="types.{{ $index }}.fields.{{ $fieldIndex }}.options" :label="__('Options')" :description="__('One option per line.')" class="sm:col-span-2" rows="3" />
                                        @endif
                                        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 sm:col-span-2">
                                            <flux:switch wire:model="types.{{ $index }}.fields.{{ $fieldIndex }}.required" :label="__('Required')" align="left" />
                                            <flux:switch wire:model="types.{{ $index }}.fields.{{ $fieldIndex }}.translatable" :label="__('Translatable')" align="left" />
                                            <flux:switch wire:model="types.{{ $index }}.fields.{{ $fieldIndex }}.column" :label="__('Show in list')" align="left" />
                                            <flux:switch wire:model="types.{{ $index }}.fields.{{ $fieldIndex }}.sortable" :label="__('Sortable')" align="left" />
                                            <flux:switch wire:model="types.{{ $index }}.fields.{{ $fieldIndex }}.searchable" :label="__('Searchable')" align="left" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
