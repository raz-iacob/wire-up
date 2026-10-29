@props(['record', 'field'])

@php
    $linked = $record->linkedRecords($field);
@endphp

@if ($linked->isNotEmpty())
    <div data-field="{{ $field['key'] }}">
        <h2 class="text-xl font-semibold tracking-tight">{{ $record->recordType->fieldLabel($field) }}</h2>
        <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
            @foreach ($linked as $linkedRecord)
                <li>
                    @if ($linkedRecord->hasDetailPage())
                        <a
                            href="{{ $linkedRecord->getUrl() }}"
                            wire:navigate
                            class="text-(--wire-accent) underline underline-offset-4"
                        >{{ $linkedRecord->title }}</a>
                    @else
                        {{ $linkedRecord->title }}
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
