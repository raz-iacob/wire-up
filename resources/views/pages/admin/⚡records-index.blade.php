<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Actions\DeleteRecordAction;
use App\Enums\ContentStatus;
use App\Enums\FieldType;
use App\Models\Record;
use App\Models\RecordType;
use App\Traits\WithSorting;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use WithPagination, WithSorting;

    public RecordType $recordType;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public string $title = '';

    public ?int $selectedId = null;

    public int $perPage = 20;

    public function mount(RecordType $recordType): void
    {
        $this->recordType = $recordType;
    }

    public function create(CreateRecordAction $action): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $record = $action->handle($this->recordType, [
            'title' => $this->title,
        ]);

        $this->redirect(route('admin.records-edit', [$this->recordType, $record]));
    }

    public function confirmDelete(int $id): void
    {
        $this->selectedId = $id;
        Flux::modal('confirm-delete')->show();
    }

    public function delete(int $id, DeleteRecordAction $action): void
    {
        $record = Record::query()
            ->where('record_type_id', $this->recordType->id)
            ->findOrFail($id);

        $action->handle($record);

        $this->selectedId = null;

        Flux::modal('confirm-delete')->close();
        Flux::toast(__(':name deleted successfully.', ['name' => $this->recordType->name]));
    }

    /** @return LengthAwarePaginator<int, Record> */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        $locale = app()->getLocale();

        $paginator = Record::query()
            ->where('record_type_id', $this->recordType->id)
            ->with('translations')
            ->when($this->hasMediaColumns(), fn (Builder $query): Builder => $query->with('media'))
            ->when($this->status, function (Builder $query, string $status): Builder {
                if ($status === ContentStatus::SCHEDULED->value) {
                    return $query->where('status', ContentStatus::PUBLISHED)
                        ->where('published_at', '>', now());
                }

                return $query->where('status', $status);
            })
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->where(
                function (Builder $inner) use ($search, $locale): void {
                    $inner->whereTranslationLike('title', $search);

                    foreach ($this->searchableFields() as $field) {
                        $path = $field['translatable']
                            ? "data->{$field['key']}->{$locale}"
                            : "data->{$field['key']}";

                        $inner->orWhereLike($path, "%{$search}%");
                    }
                }
            ));

        if ($this->sortBy === 'title') {
            $paginator->orderByTranslation('title', $this->sortDirection);
        } elseif (in_array($this->sortBy, $this->sortableKeys(), true)) {
            $field = $this->fieldByKey($this->sortBy);
            $path = ($field['translatable'] ?? false)
                ? "data->{$this->sortBy}->{$locale}"
                : "data->{$this->sortBy}";

            $paginator->orderBy($path, $this->sortDirection);
        } else {
            $paginator->orderBy($this->sortBy, $this->sortDirection);
        }

        return $paginator->paginate($this->perPage);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function columnFields(): array
    {
        return array_values(array_filter(
            $this->recordType->fields,
            fn (array $field): bool => (bool) ($field['column'] ?? false),
        ));
    }

    #[Computed]
    public function hasMultipleActiveLocales(): bool
    {
        return resolve('localization')->getActiveLocaleCodes()->count() > 1;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function displayValue(Record $record, array $field): string
    {
        $type = FieldType::tryFrom($field['type']);
        $key = $field['key'];
        $locale = app()->getLocale();

        if ($type?->isMedia()) {
            return (string) $record->media
                ->filter(fn (\App\Models\Media $media): bool => $media->pivot->role === $key)
                ->count();
        }

        $value = ($field['translatable'] ?? false)
            ? data_get($record->data, "{$key}.{$locale}")
            : data_get($record->data, $key);

        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            FieldType::BOOLEAN => $value ? __('Yes') : __('No'),
            FieldType::MONEY => \App\Services\SettingsService::current()->formatMoney(is_numeric($value) ? $value : null),
            FieldType::DATE => str($value)->substr(0, 10)->value(),
            FieldType::DATETIME => str($value)->replace('T', ' ')->substr(0, 16)->value(),
            FieldType::RICH_TEXT => str(strip_tags((string) $value))->squish()->limit(60)->value(),
            default => str((string) $value)->limit(60)->value(),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchableFields(): array
    {
        return array_values(array_filter(
            $this->recordType->fields,
            fn (array $field): bool => (bool) ($field['searchable'] ?? false)
                && ! (FieldType::tryFrom($field['type'])?->isMedia() ?? false),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function sortableKeys(): array
    {
        return array_values(array_map(
            fn (array $field): string => $field['key'],
            array_filter(
                $this->recordType->fields,
                fn (array $field): bool => (bool) ($field['sortable'] ?? false)
                    && ! (FieldType::tryFrom($field['type'])?->isMedia() ?? false),
            ),
        ));
    }

    private function hasMediaColumns(): bool
    {
        return array_any(
            $this->columnFields(),
            fn (array $field): bool => (bool) (FieldType::tryFrom($field['type'])?->isMedia()),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fieldByKey(string $key): ?array
    {
        foreach ($this->recordType->fields as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->recordType->name)
            ->layout('layouts::admin');
    }
};
?>
<div>
    <div class="space-y-6 md:space-y-8">
        <div class="flex items-center gap-3">
            <flux:modal.trigger name="add-new">
                <flux:button variant="primary" class="shrink-0" size="sm" icon="plus" iconVariant="outline">{{ __('Add') }}</flux:button>
            </flux:modal.trigger>

            <flux:dropdown position="bottom" align="start">
                <flux:button class="shrink-0" size="sm" icon="funnel" iconVariant="outline">{{ __('Filter') }}</flux:button>

                <flux:menu>
                    <flux:menu.submenu heading="{{ __('Status') }}">
                        <flux:menu.radio.group wire:model.live="status" heading="{{ __('Status') }}">
                            <flux:menu.radio value="" checked>{{ __('All') }}</flux:menu.radio>
                            @foreach(ContentStatus::cases() as $statusOption)
                            <flux:menu.radio value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu.submenu>
                </flux:menu>
            </flux:dropdown>

            <div class="w-full md:w-52 sm:shrink-0">
                <flux:input icon="magnifying-glass" wire:model.live="search" size="sm" placeholder="{{ __('Search...') }}" clearable />
            </div>
        </div>

        <flux:table class="md:w-full max-h-[calc(100dvh-12rem)]" :paginate="$this->records" container:class="max-h-[calc(100dvh-12rem)]">
            <flux:table.columns sticky class="bg-white dark:bg-zinc-800">
                <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">{{ __('Title') }}</flux:table.column>
                @foreach($this->columnFields as $field)
                    @php($fieldSortable = (bool) ($field['sortable'] ?? false) && ! (\App\Enums\FieldType::tryFrom($field['type'])?->isMedia() ?? false))
                    @php($fieldLabel = $field['label'][app()->getLocale()] ?? \Illuminate\Support\Arr::first($field['label']) ?? $field['key'])
                    @if ($fieldSortable)
                        <flux:table.column sortable :sorted="$sortBy === $field['key']" :direction="$sortDirection" wire:click="sort('{{ $field['key'] }}')">{{ $fieldLabel }}</flux:table.column>
                    @else
                        <flux:table.column>{{ $fieldLabel }}</flux:table.column>
                    @endif
                @endforeach
                @if($this->hasMultipleActiveLocales())
                <flux:table.column>{{ __('Languages') }}</flux:table.column>
                @endif
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">{{ __('Last updated') }}</flux:table.column>
                <flux:table.column class="w-10"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->records as $row)
                <flux:table.row wire:key="{{ $row->id }}">
                    <flux:table.cell>
                        <a href="{{ route('admin.records-edit', [$this->recordType, $row]) }}" class="flex items-center gap-2">
                            <flux:text variant="strong" class="hover:underline">{{ $row->title !== '' ? $row->title : __('Untitled') }}</flux:text>
                        </a>
                    </flux:table.cell>

                    @foreach($this->columnFields as $field)
                    <flux:table.cell class="whitespace-nowrap">{{ $this->displayValue($row, $field) }}</flux:table.cell>
                    @endforeach

                    @if($this->hasMultipleActiveLocales())
                    <flux:table.cell class="whitespace-nowrap">
                        <x-admin.language-codes :active="$row->published_locales" />
                    </flux:table.cell>
                    @endif

                    <flux:table.cell>
                        <flux:badge color="{{ $row->computed_status->color() }}" size="sm">
                            {{ $row->computed_status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        {{ $row->updated_at?->format('M d, Y H:i') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown class="flex justify-end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" square />
                            <flux:menu>
                                <flux:menu.item icon="pencil" href="{{ route('admin.records-edit', [$this->recordType, $row]) }}">
                                    {{ __('Edit') }}
                                </flux:menu.item>

                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $row->id }})">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="add-new" class="md:w-96">
        <flux:heading size="lg" class="mb-6">{{ __('Add a new :name', ['name' => \Illuminate\Support\Str::singular($recordType->name)]) }}</flux:heading>
        <form wire:submit="create" class="space-y-6">
            <flux:input wire:model="title" label="{{ __('Title') }}" badge="Required" autofocus />
            <div class="flex mt-6">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-delete" class="min-w-88">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="mb-6">{{ __('Confirm delete') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to delete') }}
                    "<span class="text-black dark:text-white">{{ $this->records->find($selectedId)?->title }}</span>" ?
                </flux:text>
                <flux:text>{{ __('This action cannot be reversed.') }}</flux:text>
            </div>
            <div class="flex gap-3">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button>{{ __('No, Keep it.') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete({{ $selectedId }})" variant="danger">{{ __('Yes, Delete it!') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@section('header-content')
    <flux:breadcrumbs>
        <flux:breadcrumbs.item class="pl-3 md:pl-0">{{ $recordType->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
