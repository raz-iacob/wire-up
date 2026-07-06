<?php

declare(strict_types=1);

use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    public bool $showLibrary = false;

    public string $target = '';

    public ?int $recordTypeId = null;

    public int $max = 30;

    /**
     * @var array<int, int>
     */
    public array $selectedIds = [];

    /** @var Collection<int, array<string, mixed>> */
    public Collection $records;

    public string $search = '';

    public int $perPage = 20;

    public bool $hasMore = true;

    public bool $loaded = false;

    public function mount(): void
    {
        $this->records = collect();
    }

    #[Computed]
    public function recordType(): ?RecordType
    {
        return $this->recordTypeId === null
            ? null
            : RecordType::query()->find($this->recordTypeId);
    }

    /**
     * @param  array<int, int|string>  $selectedIds
     */
    #[On('select-records')]
    public function handleSelectRecords(string $target, ?int $recordTypeId = null, int $max = 30, array $selectedIds = []): void
    {
        $this->target = $target;
        $this->recordTypeId = $recordTypeId;
        $this->max = max(1, $max);
        $this->selectedIds = array_values(array_map(intval(...), $selectedIds));
        $this->search = '';
        $this->records = collect();
        $this->showLibrary = true;

        $this->loadRecords();
    }

    public function updatedSearch(): void
    {
        $this->records = collect();
        $this->loadRecords();
    }

    public function toggle(int $recordId): void
    {
        if (in_array($recordId, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_filter(
                $this->selectedIds,
                fn (int $id): bool => $id !== $recordId,
            ));

            return;
        }

        if (count($this->selectedIds) >= $this->max) {
            return;
        }

        $this->selectedIds[] = $recordId;
    }

    public function isChosen(int $recordId): bool
    {
        return in_array($recordId, $this->selectedIds, true);
    }

    public function insert(): void
    {
        $this->dispatch(
            'records-selected',
            target: $this->target,
            ids: array_map(strval(...), $this->selectedIds),
        );

        $this->showLibrary = false;
    }

    public function loadRecords(bool $loadMore = false): void
    {
        $recordType = $this->recordType();

        if (! $recordType instanceof RecordType) {
            return;
        }

        $base = Record::query()
            ->where('record_type_id', $recordType->id)
            ->matchingSearch($this->search, $recordType);

        $offset = $loadMore ? $this->records->count() : 0;

        $items = (clone $base)
            ->with(['translations', 'slugs', 'recordType', 'media'])
            ->orderByTranslation('title', 'asc')
            ->offset($offset)
            ->limit($this->perPage)
            ->get()
            ->map(fn (Record $record): array => $this->parseRecord($record, $recordType));

        $this->records = $this->records->merge($items);

        $this->hasMore = $this->records->count() < (clone $base)->count();

        $this->loaded = true;
    }

    public function loadMore(): void
    {
        $this->loadRecords(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseRecord(Record $record, RecordType $recordType): array
    {
        return [
            'id' => $record->id,
            'title' => $record->title !== '' ? $record->title : __('Untitled'),
            'thumbnail' => $record->primaryImageUrl(200),
            'status' => [
                'label' => $record->computed_status->label(),
                'color' => $record->computed_status->color(),
            ],
            'columns' => array_map(fn (array $field): array => [
                'label' => $recordType->fieldLabel($field),
                'value' => $record->columnValue($field),
            ], $recordType->indexColumnFields()),
        ];
    }
};
?>

<div>
    <flux:modal wire:model.self="showLibrary" :closable="false" class="w-full max-w-3xl">
        @php($recordType = $this->recordType())
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <flux:heading size="lg">
                    {{ $recordType ? __('Select :name', ['name' => $recordType->name]) : __('Select records') }}
                </flux:heading>

                <div class="w-full md:w-64">
                    <flux:input icon="magnifying-glass" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search…') }}" clearable />
                </div>
            </div>

            <div
                class="-mx-2 grid min-h-120 max-h-[60vh] content-start gap-1 overflow-y-auto overscroll-contain px-2"
                wire:loading.class="opacity-60" wire:target="loadMore, updatedSearch"
            >
                @forelse ($records as $record)
                    <button
                        type="button"
                        wire:key="record-lib-{{ $record['id'] }}"
                        wire:click="toggle({{ $record['id'] }})"
                        @class([
                            'flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition',
                            'border-sky-500 bg-sky-50 dark:border-sky-500/60 dark:bg-sky-500/10' => $this->isChosen($record['id']),
                            'border-zinc-200 hover:bg-zinc-50 dark:border-white/10 dark:hover:bg-white/5' => ! $this->isChosen($record['id']),
                        ])
                    >
                        <span @class([
                            'flex size-5 shrink-0 items-center justify-center rounded-md border transition',
                            'border-sky-500 bg-sky-500 text-white' => $this->isChosen($record['id']),
                            'border-zinc-300 dark:border-white/25' => ! $this->isChosen($record['id']),
                        ])>
                            @if ($this->isChosen($record['id']))
                                <flux:icon name="check" variant="micro" class="size-3.5" />
                            @endif
                        </span>

                        <div class="size-11 shrink-0 overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 dark:border-white/10 dark:bg-white/5">
                            @if ($record['thumbnail'])
                                <img src="{{ $record['thumbnail'] }}" alt="" class="size-full object-cover" loading="lazy" />
                            @else
                                <div class="flex size-full items-center justify-center text-zinc-300 dark:text-zinc-600">
                                    <flux:icon name="photo" class="size-5" />
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <flux:heading size="sm" class="truncate">{{ $record['title'] }}</flux:heading>
                            @if ($record['columns'] !== [])
                                <flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400">
                                    {{ collect($record['columns'])->map(fn (array $column): string => $column['value'])->filter(fn (string $value): bool => $value !== '—')->implode(' · ') }}
                                </flux:text>
                            @endif
                        </div>

                        <flux:badge color="{{ $record['status']['color'] }}" size="sm" class="shrink-0">{{ $record['status']['label'] }}</flux:badge>
                    </button>
                @empty
                    @if ($loaded)
                        <div class="py-12 text-center">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No records found.') }}</flux:text>
                        </div>
                    @endif
                @endforelse

                @if ($hasMore)
                    <div wire:intersect.margin.100px="loadMore" class="h-1"></div>
                @endif
            </div>

            <div class="flex items-center justify-between gap-4">
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ count($selectedIds) }} / {{ $max }}</flux:text>

                <div class="flex items-center gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="insert">{{ __('Select') }}</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
