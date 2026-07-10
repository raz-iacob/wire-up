@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = '$wire.blocks['.json_encode((string) $index).'].content';
    $recordTypes = \App\Models\RecordType::query()->orderBy('position')->get(['id', 'name']);
    $content = data_get($block, 'content', []);
    $selectedSources = array_values(array_filter((array) data_get($content, 'sources', []), 'is_scalar'));

    $sourceLabels = [];
    foreach ($recordTypes as $recordType) {
        $sourceLabels[(string) $recordType->id] = $recordType->name;
    }
    $sourceLabels['pages'] = __('Pages');
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />

    <flux:pillbox wire:model.live="{{ $c }}.sources" multiple searchable label="{{ __('Content types to search') }}" placeholder="{{ __('Choose what visitors can search…') }}">
        @foreach ($recordTypes as $recordType)
            <flux:pillbox.option :value="(string) $recordType->id" :label="$recordType->name" />
        @endforeach
        <flux:pillbox.option value="pages" :label="__('Pages')" />
    </flux:pillbox>

    @if ($selectedSources !== [])
        <div class="flex flex-col gap-4">
            <flux:label>{{ __('Labels shown on the site') }}</flux:label>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($selectedSources as $sourceKey)
                    @if (isset($sourceLabels[$sourceKey]))
                        <x-forms.input-translated
                            name="{{ $c }}.labels.{{ $sourceKey }}"
                            :locale="$locale"
                            :multi-locale="$multiLocale"
                            label="{{ $sourceLabels[$sourceKey] }}"
                            wire:key="search-label-{{ $index }}-{{ $sourceKey }}" />
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid md:grid-cols-2 gap-4">
        <flux:radio.group wire:model.live="{{ $c }}.layout" variant="segmented" label="{{ __('Layout') }}">
            <flux:radio value="grid" icon="squares-2x2" label="{{ __('Grid') }}" />
            <flux:radio value="list" icon="list-bullet" label="{{ __('List') }}" />
        </flux:radio.group>

        <div x-show="{{ $b }}?.layout === 'grid'" x-cloak>
            <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
                @foreach ([2, 3, 4] as $columnOption)
                    <flux:radio value="{{ $columnOption }}" label="{{ $columnOption }}" />
                @endforeach
            </flux:radio.group>
        </div>
    </div>

    <x-forms.input-translated name="{{ $c }}.placeholder" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Search box placeholder') }}" />

    <flux:input type="number" min="1" max="24" wire:model.live="{{ $c }}.perType" label="{{ __('Results shown per type') }}" description="{{ __('The total match count is always shown; this limits how many cards appear under each type.') }}" />

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.live="{{ $c }}.showImage" label="{{ __('Show each result\'s image') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
    </div>
</div>
