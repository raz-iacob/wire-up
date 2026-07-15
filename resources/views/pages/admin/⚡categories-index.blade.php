<?php

declare(strict_types=1);

use App\Actions\CreateCategoryAction;
use App\Actions\DeleteCategoryAction;
use App\Models\Category;
use App\Traits\WithSorting;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use WithPagination, WithSorting;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Validate(['required', 'string', 'max:255'])]
    public string $name = '';

    public ?int $selectedId = null;

    public int $perPage = 20;

    public function create(CreateCategoryAction $action): void
    {
        $this->authorize('categories.create');

        $this->validate();

        $category = $action->handle([
            'name' => [resolve('localization')->getDefaultLocale() => $this->name],
        ]);

        $this->redirect(route('admin.categories-edit', $category));
    }

    public function confirmDelete(int $id): void
    {
        $this->selectedId = $id;

        Flux::modal('confirm-delete')->show();
    }

    public function delete(int $id, DeleteCategoryAction $action): void
    {
        $this->authorize('categories.delete');

        $action->handle(Category::query()->findOrFail($id));

        $this->selectedId = null;

        Flux::modal('confirm-delete')->close();
        Flux::toast(__('Category deleted successfully.'));
        unset($this->categories);
    }

    /** @return LengthAwarePaginator<int, Category> */
    #[Computed]
    public function categories(): LengthAwarePaginator
    {
        $paginator = Category::query()
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->whereTranslationLike('name', $search));

        if ($this->sortBy === 'name') {
            $paginator->orderByTranslation('name', $this->sortDirection);
        } else {
            $paginator->orderBy($this->sortBy, $this->sortDirection);
        }

        return $paginator->withCount(['records', 'pages'])->paginate($this->perPage);
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Categories'))
            ->layout('layouts::admin');
    }
};
?>
<div>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            @can('categories.create')
                <flux:modal.trigger name="add-new">
                    <flux:button variant="primary" class="shrink-0" size="sm" icon="plus" iconVariant="outline">{{ __('Add') }}</flux:button>
                </flux:modal.trigger>
            @endcan

            <div class="w-full md:w-52 sm:shrink-0">
                <flux:input icon="magnifying-glass" wire:model.live="search" size="sm" placeholder="{{ __('Search...') }}" clearable />
            </div>
        </div>

        <flux:table class="md:table-fixed md:w-full max-h-[calc(100dvh-12rem)]" :paginate="$this->categories" container:class="max-h-[calc(100dvh-12rem)]">
            <flux:table.columns sticky class="bg-white dark:bg-zinc-800">
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column class="w-24">{{ __('Used') }}</flux:table.column>
                <flux:table.column class="w-1/6" sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">{{ __('Last updated') }}</flux:table.column>
                <flux:table.column class="w-10"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->categories as $row)
                <flux:table.row wire:key="{{ $row->id }}">
                    <flux:table.cell>
                        <a href="{{ route('admin.categories-edit', $row) }}" wire:navigate class="flex min-w-0 items-center gap-2">
                            <flux:text variant="strong" class="truncate hover:underline">{{ $row->name !== '' ? $row->name : __('Untitled') }}</flux:text>
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>{{ $row->records_count + $row->pages_count }}</flux:table.cell>
                    <flux:table.cell>{{ $row->updated_at?->format('M d, Y H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown class="flex justify-end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" square />
                            <flux:menu>
                                @can('categories.edit')
                                    <flux:menu.item icon="pencil" href="{{ route('admin.categories-edit', $row) }}">{{ __('Edit') }}</flux:menu.item>
                                @endcan
                                @can('categories.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $row->id }})">{{ __('Delete') }}</flux:menu.item>
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
        <flux:heading size="lg" class="mb-6">{{ __('Add a new category') }}</flux:heading>
        <form wire:submit="create" class="space-y-6">
            <flux:input wire:model="name" label="{{ __('Name') }}" badge="Required" autofocus />
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
                    "<span class="text-black dark:text-white">{{ $this->categories->find($selectedId)?->name }}</span>" ?
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
        <flux:breadcrumbs.item class="pl-3 md:pl-0">{{ __('Categories') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
