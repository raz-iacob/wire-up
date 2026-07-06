@props(['records', 'layout' => 'grid', 'columns' => 3, 'showImage' => true, 'fields' => [], 'blockId'])

@php
    $gridCols = match ((int) $columns) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
@endphp

@if ($layout === 'list')
    <div class="flex flex-col divide-y divide-black/10 dark:divide-white/10 lg:w-3/4">
        @foreach ($records as $record)
            <x-site.blocks.collection-item :record="$record" :show-image="$showImage" :fields="$fields" layout="list" wire:key="collection-{{ $blockId }}-{{ $record->id }}" />
        @endforeach
    </div>
@else
    <div class="grid grid-cols-1 gap-6 {{ $gridCols }}">
        @foreach ($records as $record)
            <x-site.blocks.collection-item :record="$record" :show-image="$showImage" :fields="$fields" layout="grid" wire:key="collection-{{ $blockId }}-{{ $record->id }}" />
        @endforeach
    </div>
@endif
