@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = "\$wire.blocks[".json_encode((string) $index)."].content";
    $builtins = ['name' => __('Name'), 'email' => __('Email'), 'phone' => __('Phone'), 'subject' => __('Subject'), 'message' => __('Message')];
    $fieldTypes = ['text' => __('Text'), 'textarea' => __('Paragraph'), 'email' => __('Email'), 'tel' => __('Phone'), 'number' => __('Number'), 'select' => __('Dropdown'), 'checkbox' => __('Checkbox')];
    $customFields = data_get($block, 'content.customFields', []);
    $customIndexById = [];
    foreach ($customFields as $customIndex => $customField) {
        if (is_array($customField) && isset($customField['id'])) {
            $customIndexById[$customField['id']] = $customIndex;
        }
    }
    $fieldOrder = data_get($block, 'content.fieldOrder', array_keys($builtins));
    $fieldOrder = array_values(array_filter($fieldOrder, fn (string $token): bool => isset($builtins[$token]) || isset($customIndexById[$token])));
    $missingBuiltins = array_values(array_diff(array_keys($builtins), $fieldOrder));
@endphp

<div class="flex flex-col gap-6">
    <flux:input wire:model.lazy="{{ $c }}.formName" label="{{ __('Form name') }}" placeholder="{{ __('e.g. Massage enquiry') }}" description="{{ __('Shown in the notification email so you know which form was submitted.') }}" />

    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" toolbar="bold italic underline" />
    <x-forms.texteditor-translated name="{{ $c }}.description" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Intro text') }}" toolbar="bold italic underline | link" />

    <flux:radio.group wire:model.live="{{ $c }}.layout" variant="segmented" label="{{ __('Layout') }}">
        <flux:radio value="stacked" label="{{ __('Stacked') }}" />
        <flux:radio value="split" label="{{ __('Split') }}" />
        <flux:radio value="full" label="{{ __('Full width') }}" />
    </flux:radio.group>

    <div class="flex flex-col gap-2">
        <flux:label>{{ __('Fields') }}</flux:label>

        <div wire:sort="reorderContactFields" wire:sort:group="contact-fields-{{ $block['id'] }}" class="flex flex-col gap-3">
            @foreach ($fieldOrder as $token)
                @if (isset($builtins[$token]))
                    <flux:card
                        size="sm"
                        class="p-0! overflow-hidden"
                        wire:key="builtin-{{ $block['id'] }}-{{ $token }}"
                        wire:sort:item="{{ $block['id'] }}::{{ $token }}"
                        x-data="{ open: false }"
                    >
                        <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                            <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                                <flux:icon name="bars-3" variant="mini" />
                            </div>

                            <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                                <flux:heading size="sm" class="truncate">{{ $builtins[$token] }}</flux:heading>
                            </button>

                            <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                                <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                    <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                    <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                                </flux:button>
                                <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removeContactField('{{ $block['id'] }}', '{{ $token }}')" :tooltip="__('Remove field')" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 p-4" x-show="open" x-collapse x-cloak>
                            @include('components.admin.blocks.partials.contact-builtin', ['c' => $c, 'b' => $b, 'token' => $token, 'label' => $builtins[$token], 'locale' => $locale, 'multiLocale' => $multiLocale])
                        </div>
                    </flux:card>
                @else
                    @php($i = $customIndexById[$token])
                    @php($fieldName = \Illuminate\Support\Str::of(strip_tags((string) data_get($customFields[$i], "label.{$locale}")))->squish()->limit(40)->value())
                    <flux:card
                        size="sm"
                        class="p-0! overflow-hidden"
                        wire:key="cf-{{ $token }}"
                        wire:sort:item="{{ $block['id'] }}::{{ $token }}"
                        x-data="{ open: {{ $fieldName === '' ? 'true' : 'false' }} }"
                    >
                        <div class="flex items-center justify-between gap-3 bg-zinc-100 dark:bg-white/10 px-3 py-2">
                            <div wire:sort:handle class="cursor-grab text-zinc-400" title="{{ __('Drag to reorder') }}">
                                <flux:icon name="bars-3" variant="mini" />
                            </div>

                            <button type="button" class="flex items-center gap-2 grow min-w-0 text-left" x-on:click="open = !open">
                                <flux:heading size="sm" class="truncate">{{ $fieldName !== '' ? $fieldName : __('Custom field') }}</flux:heading>
                            </button>

                            <div wire:sort:ignore class="flex items-center gap-1 shrink-0">
                                <flux:button size="sm" variant="subtle" square x-on:click="open = !open" :tooltip="__('Toggle')">
                                    <flux:icon name="chevron-down" variant="mini" x-show="!open" />
                                    <flux:icon name="chevron-up" variant="mini" x-show="open" x-cloak />
                                </flux:button>
                                <flux:button size="sm" variant="subtle" icon="trash" square wire:click="removeContactField('{{ $block['id'] }}', '{{ $token }}')" :tooltip="__('Remove field')" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 p-4" x-show="open" x-collapse x-cloak>
                            <x-forms.input-translated name="{{ $c }}.customFields.{{ $i }}.label" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Label') }}" />

                            <flux:select wire:model.live="{{ $c }}.customFields.{{ $i }}.type" variant="listbox" label="{{ __('Type') }}">
                                @foreach ($fieldTypes as $value => $typeLabel)
                                    <flux:select.option value="{{ $value }}">{{ $typeLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <div x-show="{{ $b }}?.customFields?.[{{ $i }}]?.type === 'select'" x-cloak>
                                <flux:textarea wire:model.lazy="{{ $c }}.customFields.{{ $i }}.options" label="{{ __('Options') }}" rows="4" placeholder="{{ __('One option per line') }}" />
                            </div>

                            <flux:switch wire:model.live="{{ $c }}.customFields.{{ $i }}.required" label="{{ __('Required') }}" align="left" />

                            <div x-show="{{ $b }}?.layout === 'split'" x-cloak>
                                <flux:radio.group wire:model.live="{{ $c }}.customFields.{{ $i }}.column" variant="segmented" size="sm" label="{{ __('Column') }}">
                                    <flux:radio value="left" label="{{ __('Left') }}" />
                                    <flux:radio value="right" label="{{ __('Right') }}" />
                                </flux:radio.group>
                            </div>
                        </div>
                    </flux:card>
                @endif
            @endforeach
        </div>

        <div>
            <flux:dropdown position="bottom" align="start">
                <flux:button size="sm" icon="plus" icon-trailing="chevron-down">{{ __('Add field') }}</flux:button>
                <flux:menu>
                    @foreach ($missingBuiltins as $key)
                        <flux:menu.item wire:click="addContactBuiltin('{{ $block['id'] }}', '{{ $key }}')">{{ $builtins[$key] }}</flux:menu.item>
                    @endforeach
                    @if ($missingBuiltins !== [])
                        <flux:menu.separator />
                    @endif
                    <flux:menu.item wire:click="addContactField('{{ $block['id'] }}')">{{ __('Custom field') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <x-forms.input-translated name="{{ $c }}.submitText" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Submit button text') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.successMessage" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Success message') }}" toolbar="bold italic | link" />

    <flux:input wire:model.lazy="{{ $c }}.recipient" label="{{ __('Send submissions to') }}" placeholder="{{ __('Defaults to your site contact email') }}" description="{{ __('Separate multiple addresses with a comma.') }}" />

    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
