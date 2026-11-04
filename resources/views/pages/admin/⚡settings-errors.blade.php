<?php

declare(strict_types=1);

use App\Models\ErrorLog;
use App\Models\Role;
use App\Traits\WithBulkSelection;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use WithBulkSelection, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'error', except: null)]
    public ?int $viewingId = null;

    public bool $showDetails = false;

    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->showDetails = $this->viewing() instanceof ErrorLog;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedShowDetails(bool $open): void
    {
        if (! $open) {
            $this->viewingId = null;
        }
    }

    public function show(int $id): void
    {
        $this->viewingId = $id;
        $this->showDetails = $this->viewing() instanceof ErrorLog;
    }

    public function resolve(int $id): void
    {
        $this->authorize(Role::DEVELOPER);

        ErrorLog::query()->find($id)?->resolve(auth()->user());

        Flux::toast(__('Error marked as resolved.'), variant: 'success');
    }

    public function reopen(int $id): void
    {
        $this->authorize(Role::DEVELOPER);

        ErrorLog::query()->find($id)?->reopen();

        Flux::toast(__('Error reopened.'), variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;

        Flux::modal('delete-error')->show();
    }

    public function delete(): void
    {
        $this->authorize(Role::DEVELOPER);

        ErrorLog::query()->whereKey($this->deletingId)->delete();

        $this->deletingId = null;
        $this->viewingId = null;
        $this->showDetails = false;

        Flux::modal('delete-error')->close();
        Flux::toast(__('Error deleted.'), variant: 'success');
    }

    public function resolveSelected(): void
    {
        $this->authorize(Role::DEVELOPER);

        $errors = $this->selectedErrors()->reject(fn (ErrorLog $error): bool => $error->isResolved());

        foreach ($errors as $error) {
            $error->resolve(auth()->user());
        }

        $this->finishBulkAction(trans_choice('{0} The selected errors are already resolved.|{1} :count error marked as resolved.|[2,*] :count errors marked as resolved.', $errors->count(), ['count' => $errors->count()]));
    }

    public function reopenSelected(): void
    {
        $this->authorize(Role::DEVELOPER);

        $errors = $this->selectedErrors()->filter(fn (ErrorLog $error): bool => $error->isResolved());

        foreach ($errors as $error) {
            $error->reopen();
        }

        $this->finishBulkAction(trans_choice('{0} The selected errors are already open.|{1} :count error reopened.|[2,*] :count errors reopened.', $errors->count(), ['count' => $errors->count()]));
    }

    public function deleteSelected(): void
    {
        $this->authorize(Role::DEVELOPER);

        $count = ErrorLog::query()->whereKey($this->selectedIds())->delete();

        Flux::modal('bulk-delete')->close();

        $this->finishBulkAction(trans_choice('{0} No errors deleted.|{1} :count error deleted.|[2,*] :count errors deleted.', $count, ['count' => $count]));
    }

    /** @return LengthAwarePaginator<int, ErrorLog> */
    #[Computed]
    public function errors(): LengthAwarePaginator
    {
        return ErrorLog::query()
            ->when($this->status === '', fn (Builder $query): Builder => $query->whereNull('resolved_at'))
            ->when($this->status === 'resolved', fn (Builder $query): Builder => $query->whereNotNull('resolved_at'))
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->whereAny(['type', 'message', 'file', 'address'], 'like', "%{$search}%"))
            ->latest('last_seen_at')
            ->paginate(20);
    }

    #[Computed]
    public function viewing(): ?ErrorLog
    {
        return $this->viewingId === null ? null : ErrorLog::query()->with(['user', 'resolver'])->find($this->viewingId);
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Error log'))
            ->layout('layouts::admin');
    }

    /**
     * @return EloquentCollection<int, ErrorLog>
     */
    private function selectedErrors(): EloquentCollection
    {
        return ErrorLog::query()->whereKey($this->selectedIds())->get();
    }
};
?>

<div>
    <x-admin.settings-tabs />

    <div class="space-y-6" x-data="bulkSelection($wire)">
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
                        <flux:menu.radio value="" checked>{{ __('Unresolved') }}</flux:menu.radio>
                        <flux:menu.radio value="resolved">{{ __('Resolved') }}</flux:menu.radio>
                        <flux:menu.radio value="all">{{ __('All errors') }}</flux:menu.radio>
                    </flux:menu.radio.group>
                </flux:menu>
            </flux:dropdown>
            @if (in_array($status, ['resolved', 'all'], true))
                <x-admin.filter-chip
                    :label="$status === 'resolved' ? __('Resolved') : __('All errors')"
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
            <flux:menu.item icon="check-circle" wire:click="resolveSelected">{{ __('Resolve') }}</flux:menu.item>
            <flux:menu.item icon="arrow-path" wire:click="reopenSelected">{{ __('Reopen') }}</flux:menu.item>
            <flux:menu.separator />
            <flux:modal.trigger name="bulk-delete">
                <flux:menu.item variant="danger" icon="trash">{{ __('Delete') }}</flux:menu.item>
            </flux:modal.trigger>
        </x-admin.bulk-bar>

        <flux:checkbox.group wire:model="selected">
            <flux:table
                class="max-h-[calc(100dvh-12rem)] md:w-full md:table-fixed"
                :paginate="$this->errors"
                container:class="max-h-[calc(100dvh-12rem)]"
            >
                <flux:table.columns sticky class="bg-white dark:bg-zinc-800">
                    <flux:table.column class="w-11 ps-3!">
                        <flux:checkbox.all :aria-label="__('Select all')"
                    /></flux:table.column>
                    <flux:table.column>{{ __('Error') }}</flux:table.column>
                    <flux:table.column class="w-32 text-end">{{ __('Occurrences') }}</flux:table.column>
                    <flux:table.column class="w-40">{{ __('Last seen') }}</flux:table.column>
                    <flux:table.column class="w-28">{{ __('Status') }}</flux:table.column>
                    <flux:table.column class="w-10"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->errors as $row)
                        <flux:table.row
                            wire:key="error-{{ $row->id }}"
                            x-bind:class="{ 'bg-zinc-50 dark:bg-zinc-900': isSelected({{ $row->id }}) }"
                            x-on:dblclick="toggleRow($event, {{ $row->id }})"
                            x-on:mousedown="$event.detail > 1 && $event.preventDefault()"
                        >
                            <flux:table.cell class="ps-3!">
                                <flux:checkbox
                                    :value="$row->id"
                                    :aria-label="__('Select :title', ['title' => class_basename($row->type)])"
                                />
                            </flux:table.cell>
                            <flux:table.cell class="truncate">
                                <button
                                    type="button"
                                    wire:click="show({{ $row->id }})"
                                    class="block max-w-full text-start"
                                >
                                    <flux:text variant="strong" class="truncate hover:underline">
                                        {{ class_basename($row->type) }}: {{ $row->message }}</flux:text>
                                    <flux:text size="sm" class="truncate font-mono"
                                        >{{ $row->file }}:{{ $row->line }}</flux:text>
                                </button>
                            </flux:table.cell>
                            <flux:table.cell class="text-end tabular-nums">
                                {{ number_format($row->occurrences) }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                {{ $row->last_seen_at->format('M d, Y H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($row->isResolved())
                                    <flux:badge size="sm" color="green">{{ __('Resolved') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red">{{ __('Unresolved') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown class="flex justify-end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" square />
                                    <flux:menu>
                                        <flux:menu.item icon="eye" wire:click="show({{ $row->id }})">
                                            {{ __('View details') }}</flux:menu.item>
                                        @if ($row->isResolved())
                                            <flux:menu.item icon="arrow-path" wire:click="reopen({{ $row->id }})">
                                                {{ __('Reopen') }}</flux:menu.item>
                                        @else
                                            <flux:menu.item icon="check-circle" wire:click="resolve({{ $row->id }})">
                                                {{ __('Resolve') }}</flux:menu.item>
                                        @endif
                                        <flux:menu.separator />
                                        <flux:menu.item
                                            icon="trash"
                                            variant="danger"
                                            wire:click="confirmDelete({{ $row->id }})"
                                        >
                                            {{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="py-10 text-center">
                                {{ $status === '' && $search === '' ? __('No unresolved errors.') : __('No errors match these filters.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:checkbox.group>
    </div>

    <flux:modal name="bulk-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete the selected errors?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('They are removed from the log. If one happens again it is logged as new.') }}</flux:text>
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

    <flux:modal name="delete-error" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete error') }}</flux:heading>
                <flux:text class="mt-2">{{ __('It is removed from the log. If it happens again it is logged as new.') }}</flux:text>
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

    <flux:modal wire:model.self="showDetails" class="w-full md:max-w-3xl">
        @if ($error = $this->viewing)
            <div class="space-y-6">
                <div class="space-y-2 pe-8">
                    <div class="flex items-center gap-2">
                        @if ($error->isResolved())
                            <flux:badge size="sm" color="green">{{ __('Resolved') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="red">{{ __('Unresolved') }}</flux:badge>
                        @endif
                        <flux:text size="sm" class="font-mono">{{ $error->type }}</flux:text>
                    </div>
                    <flux:heading size="lg" class="break-words whitespace-pre-line">{{ $error->message }}</flux:heading>
                </div>

                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Where') }}</dt>
                    <dd class="font-mono break-all">{{ $error->file }}:{{ $error->line }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Last address') }}</dt>
                    <dd class="break-all">{{ $error->address ?? __('Command line or queue') }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Signed in') }}</dt>
                    <dd>
                        {{ $error->user?->visibleName() ?? ($error->user_id === null ? __('No') : __('User #:id', ['id' => $error->user_id])) }}
                    </dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Occurrences') }}</dt>
                    <dd>{{ number_format($error->occurrences) }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('First seen') }}</dt>
                    <dd>{{ $error->created_at->format('M d, Y H:i') }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Last seen') }}</dt>
                    <dd>{{ $error->last_seen_at->format('M d, Y H:i') }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Version') }}</dt>
                    <dd>
                        Wire Up {{ $error->context['version'] ?? '?' }} · PHP {{ $error->context['php'] ?? '?' }} · {{ $error->context['environment'] ?? '?' }}
                    </dd>
                    @if ($error->isResolved())
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Resolved') }}</dt>
                        <dd>
                            {{ $error->resolved_at?->format('M d, Y H:i') }}{{ $error->resolver ? ' · '.$error->resolver->visibleName() : '' }}
                        </dd>
                    @endif
                </dl>

                <div class="space-y-2">
                    <flux:heading size="sm">{{ __('Stack trace') }}</flux:heading>
                    <pre class="max-h-72 overflow-auto rounded-lg bg-zinc-100 p-3 font-mono text-xs leading-relaxed dark:bg-zinc-900">{{ implode("\n", $error->trace) }}</pre>
                </div>

                <div class="flex gap-2">
                    <flux:button
                        variant="ghost"
                        icon="trash"
                        wire:click="confirmDelete({{ $error->id }})"
                    >{{ __('Delete') }}</flux:button>
                    <flux:spacer />
                    @if ($error->isResolved())
                        <flux:button
                            icon="arrow-path"
                            wire:click="reopen({{ $error->id }})"
                        >{{ __('Reopen') }}</flux:button>
                    @else
                        <flux:button variant="primary" icon="check" wire:click="resolve({{ $error->id }})">
                            {{ __('Resolve') }}</flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('Error log') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Error log') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
