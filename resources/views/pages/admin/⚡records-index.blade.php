<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Actions\DeleteRecordAction;
use App\Actions\DuplicateRecordAction;
use App\Enums\ContentStatus;
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

    public ?int $duplicateId = null;

    public string $duplicateTitle = '';

    public int $perPage = 20;

    public function mount(RecordType $recordType): void
    {
        $this->authorize('records.'.$recordType->key.'.view');

        $this->recordType = $recordType;
    }

    public function create(CreateRecordAction $action): void
    {
        $this->authorize('records.'.$this->recordType->key.'.create');

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $record = $action->handle($this->recordType, [
            'title' => $this->title,
        ]);

        $this->redirect(route('admin.records-edit', [$this->recordType, $record]));
    }

    public function duplicate(int $id): void
    {
        $record = Record::query()
            ->where('record_type_id', $this->recordType->id)
            ->findOrFail($id);

        $this->duplicateId = $id;
        $this->duplicateTitle = 'Copy of '.$record->title;

        Flux::modal('duplicate')->show();
    }

    public function confirmDuplicate(DuplicateRecordAction $action): void
    {
        $this->authorize('records.'.$this->recordType->key.'.create');

        $this->validate(['duplicateTitle' => ['required', 'string', 'max:255']]);

        $record = Record::query()
            ->where('record_type_id', $this->recordType->id)
            ->findOrFail($this->duplicateId);

        $copy = $action->handle($record, mb_trim($this->duplicateTitle));

        $this->redirect(route('admin.records-edit', [$this->recordType, $copy]));
    }

    public function confirmDelete(int $id): void
    {
        $this->selectedId = $id;
        Flux::modal('confirm-delete')->show();
    }

    public function delete(int $id, DeleteRecordAction $action): void
    {
        $this->authorize('records.'.$this->recordType->key.'.delete');

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
            ->with(['translations', 'slugs'])
            ->when($this->recordType->hasMediaColumns() || $this->recordType->hasImageField(), fn (Builder $query): Builder => $query->with('media'))
            ->when($this->recordType->hasImageField(), fn (Builder $query): Builder => $query->with('recordType'))
            ->when($this->status, function (Builder $query, string $status): Builder {
                if ($status === ContentStatus::SCHEDULED->value) {
                    return $query->where('status', ContentStatus::PUBLISHED)
                        ->where('published_at', '>', now());
                }

                return $query->where('status', $status);
            })
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->matchingSearch($search, $this->recordType));

        if ($this->sortBy === 'title') {
            $paginator->orderByTranslation('title', $this->sortDirection);
        } elseif (in_array($this->sortBy, $this->recordType->sortableFieldKeys(), true)) {
            $field = $this->recordType->fieldByKey($this->sortBy);
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
        return $this->recordType->indexColumnFields();
    }

    #[Computed]
    public function hasMultipleActiveLocales(): bool
    {
        return resolve('localization')->getActiveLocaleCodes()->count() > 1;
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
            @can('records.'.$recordType->key.'.create')
                <flux:modal.trigger name="add-new">
                    <flux:button variant="primary" class="shrink-0" size="sm" icon="plus" iconVariant="outline">{{ __('Add') }}</flux:button>
                </flux:modal.trigger>
            @endcan

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
                    @php($fieldLabel = $this->recordType->fieldLabel($field))
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
                        <a href="{{ route('admin.records-edit', [$this->recordType, $row]) }}" class="flex items-center gap-3">
                            @if($this->recordType->hasImageField())
                            @php($thumbnail = $row->primaryImageUrl(200))
                            <div class="size-9 shrink-0 overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 dark:border-white/10 dark:bg-white/5">
                                @if($thumbnail)
                                    <img src="{{ $thumbnail }}" alt="" class="size-full object-cover" loading="lazy" />
                                @else
                                    <div class="flex size-full items-center justify-center text-zinc-300 dark:text-zinc-600">
                                        <flux:icon name="photo" class="size-4" />
                                    </div>
                                @endif
                            </div>
                            @endif
                            <flux:text variant="strong" class="hover:underline">{{ $row->title !== '' ? $row->title : __('Untitled') }}</flux:text>
                        </a>
                    </flux:table.cell>

                    @foreach($this->columnFields as $field)
                    <flux:table.cell class="whitespace-nowrap">{{ $row->columnValue($field) }}</flux:table.cell>
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
                                @if ($row->slug !== '')
                                    <flux:menu.item icon="eye" href="{{ route('record', [$recordType->slug_prefix, $row->slug]) }}" target="_blank">
                                        {{ __('View') }}
                                    </flux:menu.item>
                                @endif

                                @can('records.'.$recordType->key.'.create')
                                    <flux:menu.item icon="document-duplicate" wire:click="duplicate({{ $row->id }})">
                                        {{ __('Duplicate') }}
                                    </flux:menu.item>
                                @endcan

                                @can('records.'.$recordType->key.'.edit')
                                    <flux:menu.item icon="pencil" href="{{ route('admin.records-edit', [$this->recordType, $row]) }}">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                @endcan

                                @can('records.'.$recordType->key.'.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $row->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                @endcan
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

    <flux:modal name="duplicate" class="md:w-96">
        <form wire:submit="confirmDuplicate" class="space-y-6">
            <flux:heading size="lg">{{ __('Duplicate :name', ['name' => \Illuminate\Support\Str::singular($recordType->name)]) }}</flux:heading>
            <flux:input wire:model="duplicateTitle" label="{{ __('Title') }}" autofocus />
            <div class="flex gap-3">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="document-duplicate">{{ __('Duplicate') }}</flux:button>
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
