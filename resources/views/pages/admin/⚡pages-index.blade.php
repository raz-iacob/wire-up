<?php

declare(strict_types=1);

use Flux\Flux;
use App\Models\Page;
use Livewire\Component;
use App\Enums\PageStatus;
use App\Traits\WithSorting;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Actions\CreatePageAction;
use App\Actions\DeletePageAction;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

return new class extends Component
{
    use WithPagination, WithSorting;

    #[Url(as: 'q', except: '')] 
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public string $title = '';

    public ?int $selectedId = null;

    public int $perPage = 20;

    public function create(CreatePageAction $action): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $page = $action->handle([
            'title' => $this->title,
        ]);

        $this->redirect(route('admin.pages-edit', $page->id));
    }

    public function confirmDelete(int $id): void
    {
        $this->selectedId = $id;
        Flux::modal('confirm-delete')->show();
    }

    public function delete(int $id, DeletePageAction $action): void
    {
        $page = Page::query()->findOrFail($id);
        $action->handle($page);

        $this->selectedId = null;

        Flux::modal('confirm-delete')->close();
        Flux::toast(__('Page deleted successfully.'));
    }

    /** @return LengthAwarePaginator<int, Page> */
    #[Computed]
    public function pages(): LengthAwarePaginator
    {
        $paginator = Page::query()
            ->when($this->status, function (Builder $query, string $status): Builder {
                if ($status === PageStatus::SCHEDULED->value) {
                    return $query->where('status', PageStatus::PUBLISHED)
                        ->where('published_at', '>', now());
                }

                return $query->where('status', $status);
            })
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->whereTranslationLike('title', $search));

        if($this->sortBy === 'title') {
            $paginator->orderByTranslation('title', $this->sortDirection);
        } else {
            $paginator->orderBy($this->sortBy, $this->sortDirection);
        }
        
        return $paginator->paginate($this->perPage);
    }

    #[Computed]
    public function hasMultipleActiveLocales(): bool
    {
        return resolve('localization')->getActiveLocaleCodes()->count() > 1;
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Pages'))
            ->layout('layouts::admin');
    }
};
?>
<div>
    <div class="space-y-6 md:space-y-8">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="hidden md:block">
                <flux:heading size="xl" level="1">{{ __('Pages') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Manage your website pages') }}</flux:subheading>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-full md:w-52 sm:shrink-0">
                    <flux:select variant="listbox" wire:model.live="status">
                        <flux:select.option value="">{{ __('All Pages') }}</flux:select.option>
                        @foreach(PageStatus::cases() as $status)
                        <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="w-full md:w-52 sm:shrink-0">
                    <flux:input icon="magnifying-glass" wire:model.live="search" placeholder="{{ __('Search...') }}" clearable />
                </div>
                <flux:modal.trigger name="add-new">
                    <flux:tooltip content="{{ __('Add new') }}">
                        <flux:button square variant="primary" class="shrink-0"><flux:icon.plus variant="solid" /></flux:button>
                    </flux:tooltip>
                </flux:modal.trigger>
            </div>
        </div>

        <flux:table class="md:table-fixed md:w-full max-h-[calc(100dvh-12rem)]" :paginate="$this->pages" container:class="max-h-[calc(100dvh-12rem)]">
            <flux:table.columns sticky class="bg-white dark:bg-zinc-800">
                <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">{{ __('Title') }}</flux:table.column>
                @if($this->hasMultipleActiveLocales())
                <flux:table.column class="w-1/6">{{ __('Translations') }}</flux:table.column>
                @endif
                <flux:table.column class="w-1/6">{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-1/6" sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">{{ __('Last updated') }}</flux:table.column>
                <flux:table.column class="w-10"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->pages as $row)
                <flux:table.row wire:key="{{ $row->id }}">
                    <flux:table.cell>
                        <a href="{{ route('admin.pages-edit', $row->id) }}" class="flex items-center gap-3">
                            <flux:text variant="strong" class="hover:underline">{{ $row->title }}</flux:text>
                        </a>
                    </flux:table.cell>

                    @if($this->hasMultipleActiveLocales())
                    <flux:table.cell class="whitespace-nowrap"></flux:table.cell>
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
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom">
                            </flux:button>
                            <flux:menu>
                                <flux:menu.item icon="eye" href="{{ url($row->slug) }}" target="_blank">
                                    {{ __('Preview') }}
                                </flux:menu.item>
                                <flux:menu.item icon="pencil" href="{{ route('admin.pages-edit', $row->id) }}">
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
        <flux:heading size="lg" class="mb-6">{{ __('Add a new page') }}</flux:heading>
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
                    "<span class="text-black dark:text-white">{{ $this->pages->find($selectedId)?->title }}</span>" ?
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