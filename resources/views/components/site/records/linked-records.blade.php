@props(['record'])

@php
    $groups = collect($record->recordType->fields)
        ->filter(fn (array $field): bool => ($field['type'] ?? null) === \App\Enums\FieldType::RELATIONSHIP->value)
        ->map(fn (array $field): array => ['label' => $record->recordType->fieldLabel($field), 'records' => $record->linkedRecords($field)])
        ->filter(fn (array $group): bool => $group['records']->isNotEmpty());
@endphp

@if ($groups->isNotEmpty())
    <section class="w-full" data-linked-records>
        <div class="mx-auto flex max-w-(--wire-container) flex-col gap-8 px-(--wire-gutter) pb-16">
            @foreach ($groups as $group)
                <div>
                    <h2 class="text-xl font-semibold tracking-tight">{{ $group['label'] }}</h2>
                    <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
                        @foreach ($group['records'] as $linked)
                            <li>
                                @if ($linked->hasDetailPage())
                                    <a
                                        href="{{ $linked->getUrl() }}"
                                        wire:navigate
                                        class="text-(--wire-accent) underline underline-offset-4"
                                    >{{ $linked->title }}</a>
                                @else
                                    {{ $linked->title }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
@endif
