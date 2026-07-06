<?php

declare(strict_types=1);

use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<int, string>
     */
    #[Modelable]
    public array $recordIds = [];

    public ?int $recordTypeId = null;

    public int $max = 30;

    public bool $reorderable = true;

    public ?string $label = null;

    public string $name = 'records';

    public function mount(): void
    {
        $this->recordIds = $this->normalizeIds($this->recordIds);
    }

    #[Computed]
    public function recordType(): ?RecordType
    {
        return $this->recordTypeId === null
            ? null
            : RecordType::query()->find($this->recordTypeId);
    }

    /**
     * @return Collection<int, Record>
     */
    #[Computed]
    public function records(): Collection
    {
        $ids = array_map(intval(...), $this->recordIds);

        if ($ids === []) {
            return new Collection;
        }

        return Record::query()
            ->whereIn('id', $ids)
            ->when($this->recordTypeId !== null, fn ($query) => $query->where('record_type_id', $this->recordTypeId))
            ->with(['translations', 'recordType', 'media'])
            ->get()
            ->sortBy(fn (Record $record): int => (int) array_search($record->id, $ids, true))
            ->values();
    }

    public function openLibrary(): void
    {
        $this->dispatch(
            'select-records',
            target: $this->name,
            recordTypeId: $this->recordTypeId,
            max: $this->max,
            selectedIds: $this->recordIds,
        );
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    #[On('records-selected')]
    public function handleRecordsSelected(string $target, array $ids): void
    {
        if ($target !== $this->name) {
            return;
        }

        $this->recordIds = array_slice($this->normalizeIds($ids), 0, $this->max);
    }

    public function remove(string $recordId): void
    {
        $this->recordIds = array_values(array_filter(
            $this->recordIds,
            fn (string $id): bool => $id !== $recordId,
        ));
    }

    public function reorder(string $recordId, int $position): void
    {
        if (! $this->reorderable) {
            return;
        }

        $from = array_search($recordId, $this->recordIds, true);

        if ($from === false) {
            return;
        }

        $moved = $this->recordIds[$from];
        array_splice($this->recordIds, $from, 1);
        array_splice($this->recordIds, max(0, min($position, count($this->recordIds))), 0, [$moved]);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, string>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            $id = (string) $id;

            if (! in_array($id, $normalized, true)) {
                $normalized[] = $id;
            }
        }

        return $normalized;
    }
};
?>

<div>
    @php($recordType = $this->recordType())
    @php($selected = $this->records())

    @if ($label)
        <flux:label class="mb-2 block">{{ $label }}</flux:label>
    @endif

    <flux:card class="p-4">
        @if (! $recordType instanceof RecordType)
            <flux:text variant="subtle">{{ __('Choose a content type first.') }}</flux:text>
        @else
            @if ($selected->isNotEmpty())
                <div
                    class="mb-4 flex flex-col gap-2"
                    @if ($reorderable) wire:sort="reorder" @endif
                >
                    @foreach ($selected as $record)
                        <div
                            wire:key="record-sel-{{ $this->name }}-{{ $record->id }}"
                            @if ($reorderable) wire:sort:item="{{ $record->id }}" @endif
                            class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50/80 px-2 py-1.5 dark:border-white/10 dark:bg-white/5"
                        >
                            @if ($reorderable)
                                <button
                                    type="button"
                                    wire:sort:handle
                                    aria-label="{{ __('Drag to reorder') }}"
                                    class="shrink-0 cursor-grab text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                                >
                                    <flux:icon name="bars-3" class="size-5" />
                                </button>
                            @endif

                            <div class="size-9 shrink-0 overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 dark:border-white/10 dark:bg-white/10">
                                @php($thumbnail = $record->primaryImageUrl(200))
                                @if ($thumbnail)
                                    <img src="{{ $thumbnail }}" alt="" class="size-full object-cover" loading="lazy" />
                                @else
                                    <div class="flex size-full items-center justify-center text-zinc-300 dark:text-zinc-600">
                                        <flux:icon name="photo" class="size-4" />
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <flux:heading size="sm" class="truncate">{{ $record->title !== '' ? $record->title : __('Untitled') }}</flux:heading>
                            </div>

                            <flux:button
                                size="sm"
                                variant="subtle"
                                square
                                icon="x-mark"
                                :tooltip="__('Remove')"
                                wire:click="remove('{{ $record->id }}')"
                            />
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center justify-between gap-4">
                @if (count($this->recordIds) < $this->max)
                    <flux:button variant="filled" size="sm" icon="plus" wire:click="openLibrary">
                        {{ $selected->isEmpty() ? __('Select :name', ['name' => $recordType->name]) : __('Add more') }}
                    </flux:button>
                @else
                    <flux:text variant="subtle" class="text-sm">{{ __('Maximum of :max reached.', ['max' => $this->max]) }}</flux:text>
                @endif

                @if ($selected->isNotEmpty() && $reorderable)
                    <flux:text variant="subtle" class="text-sm">{{ __('Drag to reorder') }}</flux:text>
                @endif
            </div>
        @endif
    </flux:card>
</div>
