@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $b = '$wire.blocks['.json_encode((string) $index).'].content';
    $content = data_get($block, 'content', []);
    $selectedTypeId = data_get($content, 'recordTypeId');
    $source = data_get($content, 'source', 'latest');
    $linkType = data_get($content, 'button.link.type', 'url');

    $recordTypes = \App\Models\RecordType::query()->orderBy('position')->get(['id', 'name']);
    $categories = \App\Models\Category::query()->with('translations')->get()->sortBy(fn ($category) => $category->name)->values();

    $selectedType = $selectedTypeId ? \App\Models\RecordType::query()->find($selectedTypeId) : null;
    $displayFields = $selectedType ? $selectedType->displayableFields() : [];
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />

    <flux:select wire:model.live="{{ $c }}.recordTypeId" variant="listbox" label="{{ __('Content type') }}" placeholder="{{ __('Choose a content type…') }}">
        @foreach ($recordTypes as $recordType)
            <flux:select.option :value="$recordType->id">{{ $recordType->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:radio.group wire:model.live="{{ $c }}.source" variant="segmented" label="{{ __('Show') }}">
        <flux:radio value="latest" label="{{ __('Latest') }}" />
        <flux:radio value="category" label="{{ __('By category') }}" />
        <flux:radio value="manual" label="{{ __('Hand-picked') }}" />
    </flux:radio.group>

    <div x-show="{{ $b }}?.source === 'latest'" x-cloak class="grid md:grid-cols-2 gap-4">
        <flux:input type="number" min="1" max="100" wire:model.live="{{ $c }}.limit" label="{{ __('Maximum records') }}" />
    </div>

    <div x-show="{{ $b }}?.source === 'category'" x-cloak class="grid md:grid-cols-2 gap-4">
        <flux:select wire:model="{{ $c }}.categoryId" variant="listbox" searchable label="{{ __('Category') }}" placeholder="{{ __('Choose a category…') }}">
            @foreach ($categories as $category)
                <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input type="number" min="1" max="100" wire:model.live="{{ $c }}.limit" label="{{ __('Maximum records') }}" />
    </div>

    <div x-show="{{ $b }}?.source === 'manual'" x-cloak>
        @if ($selectedTypeId)
            <flux:label class="mb-2 block">{{ __('Records') }}</flux:label>
            <livewire:admin.blocks.record-picker
                :block-id="$block['id']"
                :record-type-id="(int) $selectedTypeId"
                :value="data_get($content, 'recordIds', [])"
                :max="30"
                wire:key="record-picker-{{ $block['id'] }}-{{ $selectedTypeId }}" />
        @else
            <flux:text variant="subtle">{{ __('Choose a content type first.') }}</flux:text>
        @endif
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <flux:radio.group wire:model.live="{{ $c }}.layout" variant="segmented" label="{{ __('Layout') }}">
            <flux:radio value="grid" icon="squares-2x2" label="{{ __('Grid') }}" />
            <flux:radio value="list" icon="list-bullet" label="{{ __('List') }}" />
            <flux:radio value="carousel" icon="view-columns" label="{{ __('Carousel') }}" />
        </flux:radio.group>

        <div x-show="{{ $b }}?.layout === 'grid'" x-cloak>
            <flux:radio.group wire:model.live="{{ $c }}.columns" variant="segmented" label="{{ __('Columns') }}">
                @foreach ([2, 3, 4] as $columnOption)
                    <flux:radio value="{{ $columnOption }}" label="{{ $columnOption }}" />
                @endforeach
            </flux:radio.group>
        </div>
    </div>

    @if ($selectedTypeId && $displayFields !== [])
        <flux:pillbox wire:model="{{ $c }}.fields" multiple searchable label="{{ __('Extra fields to show') }}" placeholder="{{ __('Choose fields to display below the description…') }}">
            @foreach ($displayFields as $field)
                <flux:pillbox.option :value="$field['key']" :label="$selectedType->fieldLabel($field)" />
            @endforeach
        </flux:pillbox>
    @endif

    <div class="flex flex-col gap-4">
        <flux:switch wire:model.live="{{ $c }}.showImage" label="{{ __('Show each record\'s image') }}" align="left" />
        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
    </div>

    <flux:switch wire:model.live="{{ $c }}.button.enabled" label="{{ __('Show a “view more” button') }}" align="left" />

    <div x-show="{{ $b }}?.button?.enabled" x-cloak class="grid md:grid-cols-2 gap-4">
        <x-forms.input-translated name="{{ $c }}.button.text" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Button text') }}" />

        <flux:select wire:model.live="{{ $c }}.button.link.type" variant="listbox" label="{{ __('Link to') }}">
            <flux:select.option value="page">{{ __('A page') }}</flux:select.option>
            <flux:select.option value="url">{{ __('External URL') }}</flux:select.option>
            <flux:select.option value="anchor">{{ __('Section on this page') }}</flux:select.option>
        </flux:select>

        <div class="col-span-2">
            @if ($linkType === 'page')
                <flux:select wire:model="{{ $c }}.button.link.value" variant="listbox" searchable placeholder="{{ __('Choose a page') }}" label="{{ __('Page') }}">
                    @foreach ($pageOptions as $pageId => $pageTitle)
                        <flux:select.option value="{{ $pageId }}">{{ $pageTitle }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif ($linkType === 'anchor')
                <flux:input wire:model.lazy="{{ $c }}.button.link.value" label="{{ __('Section anchor') }}" placeholder="#contact" />
            @else
                <div class="flex flex-col gap-3">
                    <flux:input wire:model.lazy="{{ $c }}.button.link.value" label="{{ __('URL') }}" placeholder="https://example.com" />
                    <flux:switch wire:model.lazy="{{ $c }}.button.link.newTab" label="{{ __('Open in a new tab') }}" align="left" />
                </div>
            @endif
        </div>
    </div>
</div>
