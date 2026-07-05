<?php

declare(strict_types=1);

use App\Actions\DeleteSubmissionAction;
use App\Actions\MarkSubmissionReadAction;
use App\Models\Submission;
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

    /** @return LengthAwarePaginator<int, Submission> */
    #[Computed]
    public function submissions(): LengthAwarePaginator
    {
        return Submission::query()
            ->when($this->status === 'unread', fn (Builder $query): Builder => $query->whereNull('read_at'))
            ->when($this->status === 'read', fn (Builder $query): Builder => $query->whereNotNull('read_at'))
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->whereAny(['name', 'email', 'form_name'], 'like', "%{$search}%"))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Inbox'))
            ->layout('layouts::admin');
    }
};
?>
<div>
    <div class="space-y-6 md:space-y-8">
        <div class="flex items-center gap-3">
            <flux:dropdown position="bottom" align="start">
                <flux:button class="shrink-0" size="sm" icon="funnel" iconVariant="outline">{{ __('Filter') }}</flux:button>

                <flux:menu>
                    <flux:menu.radio.group wire:model.live="status" heading="{{ __('Status') }}">
                        <flux:menu.radio value="" checked>{{ __('All') }}</flux:menu.radio>
                        <flux:menu.radio value="unread">{{ __('Unread') }}</flux:menu.radio>
                        <flux:menu.radio value="read">{{ __('Read') }}</flux:menu.radio>
                    </flux:menu.radio.group>
                </flux:menu>
            </flux:dropdown>

            <div class="w-full md:w-52 sm:shrink-0">
                <flux:input icon="magnifying-glass" wire:model.live="search" size="sm" placeholder="{{ __('Search...') }}" clearable />
            </div>
        </div>

        <flux:table class="md:table-fixed md:w-full max-h-[calc(100dvh-12rem)]" :paginate="$this->submissions" container:class="max-h-[calc(100dvh-12rem)]">
            <flux:table.columns sticky class="bg-white dark:bg-zinc-800">
                <flux:table.column class="w-6"></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">{{ __('Email') }}</flux:table.column>
                <flux:table.column class="w-1/6">{{ __('Form') }}</flux:table.column>
                <flux:table.column class="w-1/6" sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Added on') }}</flux:table.column>
                <flux:table.column class="w-10"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->submissions as $row)
                <flux:table.row wire:key="{{ $row->id }}">
                    <flux:table.cell>
                        @unless ($row->isRead())
                            <flux:tooltip content="{{ __('Unread') }}">
                                <span class="inline-block size-2.5 rounded-full bg-accent" aria-label="{{ __('Unread') }}"></span>
                            </flux:tooltip>
                        @endunless
                    </flux:table.cell>

                    <flux:table.cell>
                        <a href="{{ route('admin.inbox-show', $row->id) }}" wire:navigate>
                            <flux:text :variant="$row->isRead() ? 'default' : 'strong'" class="hover:underline">{{ $row->name ?: __('Unknown') }}</flux:text>
                        </a>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $row->email ?: '—' }}</flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $row->form_name ?: '—' }}</flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $row->created_at?->format('M d, Y H:i') }}</flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown class="flex justify-end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" square />
                            <flux:menu>
                                <flux:menu.item icon="eye" href="{{ route('admin.inbox-show', $row->id) }}" wire:navigate>
                                    {{ __('View') }}
                                </flux:menu.item>
                                @if ($row->isRead())
                                    <flux:menu.item icon="envelope" wire:click="toggleRead({{ $row->id }})">
                                        {{ __('Mark as unread') }}
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item icon="envelope-open" wire:click="toggleRead({{ $row->id }})">
                                        {{ __('Mark as read') }}
                                    </flux:menu.item>
                                @endif
                                @can('inbox.delete')
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
