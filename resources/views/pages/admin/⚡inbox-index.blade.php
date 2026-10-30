<?php

declare(strict_types=1);

use App\Actions\DeleteSubmissionAction;
use App\Actions\MarkSubmissionReadAction;
use App\Models\Submission;
use App\Services\CsvExport;
use App\Traits\WithBulkSelection;
use App\Traits\WithSorting;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

return new class extends Component
{
    use WithBulkSelection, WithPagination, WithSorting;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public ?int $selectedId = null;

    public int $perPage = 20;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function toggleRead(int $id, MarkSubmissionReadAction $action): void
    {
        $submission = Submission::query()->find($id);

        if ($submission === null) {
            return;
        }

        $action->handle($submission, ! $submission->isRead());
    }

    public function confirmDelete(int $id): void
    {
        $this->selectedId = $id;

        Flux::modal('delete-submission')->show();
    }

    public function delete(DeleteSubmissionAction $action): void
    {
        $this->authorize('inbox.delete');

        $submission = Submission::query()->find($this->selectedId);

        if ($submission !== null) {
            $action->handle($submission);
        }

        $this->selectedId = null;

        Flux::modal('delete-submission')->close();
        Flux::toast(__('Message deleted.'), variant: 'success');
    }

    public function markSelectedRead(MarkSubmissionReadAction $action): void
    {
        $submissions = $this->selectedSubmissions()->reject(fn (Submission $submission): bool => $submission->isRead());

        foreach ($submissions as $submission) {
            $action->handle($submission);
        }

        $this->finishBulkAction(trans_choice('{0} The selected messages are already read.|{1} :count message marked as read.|[2,*] :count messages marked as read.', $submissions->count(), ['count' => $submissions->count()]));
    }

    public function markSelectedUnread(MarkSubmissionReadAction $action): void
    {
        $submissions = $this->selectedSubmissions()->filter(fn (Submission $submission): bool => $submission->isRead());

        foreach ($submissions as $submission) {
            $action->handle($submission, false);
        }

        $this->finishBulkAction(trans_choice('{0} The selected messages are already unread.|{1} :count message marked as unread.|[2,*] :count messages marked as unread.', $submissions->count(), ['count' => $submissions->count()]));
    }

    public function deleteSelected(DeleteSubmissionAction $action): void
    {
        $this->authorize('inbox.delete');

        $submissions = $this->selectedSubmissions();

        foreach ($submissions as $submission) {
            $action->handle($submission);
        }

        Flux::modal('bulk-delete')->close();

        $this->finishBulkAction(trans_choice('{0} No messages deleted.|{1} :count message deleted.|[2,*] :count messages deleted.', $submissions->count(), ['count' => $submissions->count()]));
    }

    public function export(CsvExport $csv): StreamedResponse
    {
        $this->authorize('inbox.view');

        $ids = $this->selectedIds();

        $submissions = $this->submissionsQuery()
            ->with('page.translations')
            ->when($ids !== [], fn (Builder $query): Builder => $query->whereKey($ids))
            ->get();

        $labels = $submissions
            ->flatMap(fn (Submission $submission): array => array_keys($this->extraFields($submission)))
            ->unique()
            ->values()
            ->all();

        $headers = [__('Date'), __('Form'), __('Name'), __('Email'), __('Phone'), __('Subject'), __('Message'), ...$labels, __('Page'), __('Country')];

        $rows = $submissions->map(function (Submission $submission) use ($labels): array {
            $extra = $this->extraFields($submission);

            return [
                $submission->created_at->format('Y-m-d H:i'),
                $submission->form_name,
                $submission->name,
                $submission->email,
                $submission->phone,
                $submission->subject,
                $submission->message,
                ...array_map(fn (string $label): string => $extra[$label] ?? '', $labels),
                $submission->page?->title,
                $submission->countryName(),
            ];
        })->all();

        return $csv->download('inbox-'.now()->format('Y-m-d').'.csv', $headers, $rows);
    }

    /** @return LengthAwarePaginator<int, Submission> */
    #[Computed]
    public function submissions(): LengthAwarePaginator
    {
        return $this->submissionsQuery()->paginate($this->perPage);
    }

    #[Computed]
    public function canDelete(): bool
    {
        return auth()->user()?->can('inbox.delete') ?? false;
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Inbox'))
            ->layout('layouts::admin');
    }

    /**
     * @return EloquentBuilder<Submission>
     */
    private function submissionsQuery(): EloquentBuilder
    {
        return Submission::query()
            ->when($this->status === 'unread', fn (Builder $query): Builder => $query->whereNull('read_at'))
            ->when($this->status === 'read', fn (Builder $query): Builder => $query->whereNotNull('read_at'))
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->whereAny(['name', 'email', 'form_name'], 'like', "%{$search}%"))
            ->orderBy($this->sortBy, $this->sortDirection);
    }

    /**
     * @return EloquentCollection<int, Submission>
     */
    private function selectedSubmissions(): EloquentCollection
    {
        return Submission::query()->whereKey($this->selectedIds())->get();
    }

    /**
     * @return array<string, string>
     */
    private function extraFields(Submission $submission): array
    {
        $fields = [];

        foreach (is_array($submission->metadata) ? $submission->metadata : [] as $field) {
            $label = is_array($field) && is_string($field['label'] ?? null) ? $field['label'] : '';
            $value = is_array($field) ? ($field['value'] ?? null) : null;

            if ($label === '' || ! is_scalar($value)) {
                continue;
            }

            $fields[$label] = is_bool($value) ? ($value ? __('Yes') : __('No')) : (string) $value;
        }

        return $fields;
    }
};
?>
<div>
    <div class="space-y-6">
        <div x-show="$wire.selected.length === 0" class="flex flex-wrap items-center gap-3">
            <flux:dropdown position="bottom" align="start">
                <flux:button
                    class="shrink-0"
                    size="sm"
                    icon="funnel"
                    iconVariant="outline"
                >{{ __('Filter') }}</flux:button>

                <flux:menu>
                    <flux:menu.radio.group wire:model.live="status" heading="{{ __('Status') }}">
                        <flux:menu.radio value="" checked>{{ __('All') }}</flux:menu.radio>
                        <flux:menu.radio value="unread">{{ __('Unread') }}</flux:menu.radio>
                        <flux:menu.radio value="read">{{ __('Read') }}</flux:menu.radio>
                    </flux:menu.radio.group>
                    @if ($status !== '')
                        <flux:menu.separator />
                        <flux:menu.item icon="x-mark" wire:click="$set('status', '')">
                            {{ __('Clear filters') }}</flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
            <flux:button
                class="shrink-0"
                size="sm"
                icon="arrow-down-tray"
                iconVariant="outline"
                wire:click="export"
            >{{ __('Export') }}</flux:button>
            @if (in_array($status, ['unread', 'read'], true))
                <x-admin.filter-chip
                    :label="$status === 'unread' ? __('Unread') : __('Read')"
                    wire:click="$set('status', '')"
                />
            @endif

            <div class="ms-auto min-w-40 flex-1 sm:w-52 sm:flex-none">
                <flux:input
                    icon="magnifying-glass"
                    wire:model.live="search"
                    size="sm"
                    placeholder="{{ __('Search...') }}"
                    clearable
                />
            </div>
        </div>

        <x-admin.bulk-bar>
            <flux:button
                size="sm"
                icon="envelope-open"
                iconVariant="outline"
                wire:click="markSelectedRead"
            >{{ __('Mark as read') }}</flux:button>
            <flux:button
                size="sm"
                icon="envelope"
                iconVariant="outline"
                wire:click="markSelectedUnread"
            >{{ __('Mark as unread') }}</flux:button>
            <flux:button
                size="sm"
                icon="arrow-down-tray"
                iconVariant="outline"
                wire:click="export"
            >{{ __('Export') }}</flux:button>
            @if ($this->canDelete)
                <flux:modal.trigger name="bulk-delete">
                    <flux:button
                        size="sm"
                        variant="danger"
                        icon="trash"
                        iconVariant="outline"
                    >{{ __('Delete') }}</flux:button>
                </flux:modal.trigger>
            @endif
        </x-admin.bulk-bar>

        <flux:checkbox.group wire:model="selected">
            <flux:table
                class="max-h-[calc(100dvh-12rem)] md:w-full md:table-fixed"
                :paginate="$this->submissions"
                container:class="max-h-[calc(100dvh-12rem)]"
            >
                <flux:table.columns sticky class="bg-white dark:bg-zinc-800">
                    <flux:table.column class="w-8">
                        <flux:checkbox.all :aria-label="__('Select all')"
                    /></flux:table.column>
                    <flux:table.column class="w-6"></flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'name'"
                        :direction="$sortDirection"
                        wire:click="sort('name')"
                    >
                        {{ __('Name') }}</flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'email'"
                        :direction="$sortDirection"
                        wire:click="sort('email')"
                    >
                        {{ __('Email') }}</flux:table.column>
                    <flux:table.column class="w-1/6">{{ __('Form') }}</flux:table.column>
                    <flux:table.column
                        class="w-1/6"
                        sortable
                        :sorted="$sortBy === 'created_at'"
                        :direction="$sortDirection"
                        wire:click="sort('created_at')"
                    >
                        {{ __('Added on') }}</flux:table.column>
                    <flux:table.column class="w-10"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->submissions as $row)
                        <flux:table.row wire:key="{{ $row->id }}">
                            <flux:table.cell>
                                <flux:checkbox
                                    :value="$row->id"
                                    :aria-label="__('Select :title', ['title' => $row->name ?: __('Unknown')])"
                                />
                            </flux:table.cell>
                            <flux:table.cell>
                                @unless ($row->isRead())
                                    <flux:tooltip content="{{ __('Unread') }}">
                                        <span
                                            class="bg-accent inline-block size-2.5 rounded-full"
                                            aria-label="{{ __('Unread') }}"
                                        ></span>
                                    </flux:tooltip>
                                @endunless
                            </flux:table.cell>

                            <flux:table.cell class="truncate">
                                <a href="{{ route('admin.inbox-show', $row->id) }}" wire:navigate>
                                    <flux:text
                                        :variant="$row->isRead() ? 'default' : 'strong'"
                                        class="truncate hover:underline"
                                    >{{ $row->name ?: __('Unknown') }}</flux:text>
                                </a>
                            </flux:table.cell>

                            <flux:table.cell class="truncate">{{ $row->email ?: '—' }}</flux:table.cell>

                            <flux:table.cell class="truncate">{{ $row->form_name ?: '—' }}</flux:table.cell>

                            <flux:table.cell class="whitespace-nowrap">
                                {{ $row->created_at?->format('M d, Y H:i') }}</flux:table.cell>

                            <flux:table.cell>
                                <flux:dropdown class="flex justify-end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" square />
                                    <flux:menu>
                                        <flux:menu.item
                                            icon="eye"
                                            href="{{ route('admin.inbox-show', $row->id) }}"
                                            wire:navigate
                                        >
                                            {{ __('View') }}
                                        </flux:menu.item>
                                        @if ($row->isRead())
                                            <flux:menu.item icon="envelope" wire:click="toggleRead({{ $row->id }})">
                                                {{ __('Mark as unread') }}
                                            </flux:menu.item>
                                        @else
                                            <flux:menu.item
                                                icon="envelope-open"
                                                wire:click="toggleRead({{ $row->id }})"
                                            >
                                                {{ __('Mark as read') }}
                                            </flux:menu.item>
                                        @endif
                                        @can('inbox.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                wire:click="confirmDelete({{ $row->id }})"
                                            >
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
        </flux:checkbox.group>
    </div>

    @if ($this->canDelete)
        <flux:modal name="bulk-delete" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete the selected messages?') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('This cannot be undone.') }}</flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="deleteSelected">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    <flux:modal name="delete-submission" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete message') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Are you sure you want to delete this message? This cannot be undone.') }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@section('header-content')
    <flux:breadcrumbs>
        <flux:breadcrumbs.item class="pl-3 md:pl-0">{{ __('Inbox') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
