<?php

declare(strict_types=1);

use App\Actions\AddToCategoryAction;
use App\Actions\CreatePageAction;
use App\Actions\DeletePageAction;
use App\Actions\DuplicatePageAction;
use App\Actions\UpdatePageAction;
use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Settings;
use App\Services\SettingsService;
use App\Traits\WithBulkSelection;
use App\Traits\WithSorting;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use WithBulkSelection, WithPagination, WithSorting;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Validate(['required', 'string', 'max:255'])]
    public string $title = '';

    public ?int $selectedId = null;

    public ?int $duplicateId = null;

    #[Validate(['required', 'string', 'max:255'])]
    public string $duplicateTitle = '';

    public int $perPage = 20;

    public ?int $bulkCategoryId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function create(CreatePageAction $action): void
    {
        $this->authorize('pages.create');

        $this->validateOnly('title');

        $page = $action->handle([
            'title' => $this->title,
        ]);

        $this->redirect(route('admin.pages-edit', $page->id));
    }

    public function duplicate(int $id): void
    {
        $page = Page::query()->findOrFail($id);

        $this->duplicateId = $id;
        $this->duplicateTitle = 'Copy of '.$page->title;

        Flux::modal('duplicate')->show();
    }

    public function confirmDuplicate(DuplicatePageAction $action): void
    {
        $this->authorize('pages.create');

        $this->validateOnly('duplicateTitle');

        $copy = $action->handle(Page::query()->findOrFail($this->duplicateId), mb_trim($this->duplicateTitle));

        $this->redirect(route('admin.pages-edit', $copy->id));
    }

    public function confirmDelete(int $id): void
    {
        $this->selectedId = $id;
        Flux::modal('confirm-delete')->show();
    }

    public function delete(int $id, DeletePageAction $action): void
    {
        $this->authorize('pages.delete');

        $page = Page::query()->findOrFail($id);

        if ($page->id === $this->configuredHomePageId()) {
            Flux::modal('confirm-delete')->close();
            Flux::toast(__('The homepage can’t be deleted. Choose another homepage in Settings → General first.'), variant: 'danger');

            return;
        }

        $action->handle($page);

        $this->selectedId = null;

        Flux::modal('confirm-delete')->close();
        Flux::toast(__('Page deleted successfully.'));
    }

    public function publishSelected(UpdatePageAction $action): void
    {
        $this->authorize('pages.edit');

        $pages = $this->selectedPages()->reject(fn (Page $page): bool => $page->computed_status === ContentStatus::PUBLISHED);

        foreach ($pages as $page) {
            $action->handle($page, ['status' => ContentStatus::PUBLISHED]);
        }

        $this->finishBulkAction(trans_choice('{0} The selected pages are already published.|{1} :count page published.|[2,*] :count pages published.', $pages->count(), ['count' => $pages->count()]));
    }

    public function unpublishSelected(UpdatePageAction $action): void
    {
        $this->authorize('pages.edit');

        $pages = $this->selectedPages()->reject(fn (Page $page): bool => $page->status === ContentStatus::DRAFT);

        foreach ($pages as $page) {
            $action->handle($page, ['status' => ContentStatus::DRAFT]);
        }

        $this->finishBulkAction(trans_choice('{0} The selected pages are already drafts.|{1} :count page unpublished.|[2,*] :count pages unpublished.', $pages->count(), ['count' => $pages->count()]));
    }

    public function addSelectedToCategory(AddToCategoryAction $action): void
    {
        $this->authorize('pages.edit');

        $this->validate(
            ['bulkCategoryId' => ['required', 'integer', Rule::exists('categories', 'id')]],
            attributes: ['bulkCategoryId' => __('category')],
        );

        $category = Category::query()->findOrFail($this->bulkCategoryId);
        $added = $this->selectedPages()->filter(fn (Page $page): bool => $action->handle($page, $category))->count();

        $this->bulkCategoryId = null;

        Flux::modal('bulk-category')->close();

        $this->finishBulkAction(trans_choice('{0} The selected pages are already in :category.|{1} :count page added to :category.|[2,*] :count pages added to :category.', $added, ['count' => $added, 'category' => $category->name]));
    }

    public function deleteSelected(DeletePageAction $action): void
    {
        $this->authorize('pages.delete');

        $homePageId = $this->configuredHomePageId();
        [$kept, $pages] = $this->selectedPages()->partition(fn (Page $page): bool => $page->id === $homePageId);

        foreach ($pages as $page) {
            $action->handle($page);
        }

        Flux::modal('bulk-delete')->close();

        $message = trans_choice('{0} No pages deleted.|{1} :count page deleted.|[2,*] :count pages deleted.', $pages->count(), ['count' => $pages->count()]);

        $this->finishBulkAction(
            $kept->isEmpty() ? $message : $message.' '.__('The homepage was kept. Choose another homepage in Settings → General to delete it.'),
            $kept->isEmpty() ? null : 'warning',
        );
    }

    /** @return LengthAwarePaginator<int, Page> */
    #[Computed]
    public function pages(): LengthAwarePaginator
    {
        $paginator = Page::query()
            ->with('translations')
            ->when($this->status, function (Builder $query, string $status): Builder {
                if ($status === ContentStatus::SCHEDULED->value) {
                    return $query->where('status', ContentStatus::PUBLISHED)
                        ->where('published_at', '>', now());
                }

                return $query->where('status', $status);
            })
            ->when($this->search, fn (Builder $query, string $search): Builder => $query->whereTranslationLike('title', $search));

        if ($this->sortBy === 'title') {
            $paginator->orderByTranslation('title', $this->sortDirection);
        } else {
            $paginator->orderBy($this->sortBy, $this->sortDirection);
        }

        return $paginator->paginate($this->perPage);
    }

    /**
     * @return EloquentCollection<int, Category>
     */
    #[Computed]
    public function categoryOptions(): EloquentCollection
    {
        return Category::query()
            ->with('translations')
            ->get()
            ->sortBy(fn (Category $category): string => $category->name)
            ->values();
    }

    #[Computed]
    public function bulkEnabled(): bool
    {
        return auth()->user()?->canAny(['pages.edit', 'pages.delete']) ?? false;
    }

    #[Computed]
    public function hasMultipleActiveLocales(): bool
    {
        return resolve('localization')->getActiveLocaleCodes()->count() > 1;
    }

    #[Computed]
    public function configuredHomePageId(): int
    {
        return (int) Settings::get('home_page_id');
    }

    #[Computed]
    public function homePageId(): ?int
    {
        return SettingsService::current()->homePageId();
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Pages'))
            ->layout('layouts::admin');
    }

    /**
     * @return EloquentCollection<int, Page>
     */
    private function selectedPages(): EloquentCollection
    {
        return Page::query()->with('translations')->whereKey($this->selectedIds())->get();
    }
};
?>
<div>
    <div class="space-y-6">
        <div x-show="$wire.selected.length === 0" class="flex flex-wrap items-center gap-3">
            @can('pages.create')
                <flux:modal.trigger name="add-new">
                    <flux:button
                        variant="primary"
                        class="shrink-0"
                        size="sm"
                        icon="plus"
                        iconVariant="outline"
                    >{{ __('Add') }}</flux:button>
                </flux:modal.trigger>
            @endcan

            <flux:dropdown position="bottom" align="start">
                <flux:button
                    class="shrink-0"
                    size="sm"
                    icon="funnel"
                    iconVariant="outline"
                >{{ __('Filter') }}</flux:button>

                <flux:menu>
                    <flux:menu.submenu heading="{{ __('Status') }}">
                        <flux:menu.radio.group wire:model.live="status" heading="{{ __('Status') }}">
                            <flux:menu.radio value="" checked>{{ __('All') }}</flux:menu.radio>
                            @foreach (ContentStatus::cases() as $statusOption)
                                <flux:menu.radio value="{{ $statusOption->value }}">
                                    {{ $statusOption->label() }}</flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu.submenu>
                    @if ($status !== '')
                        <flux:menu.separator />
                        <flux:menu.item icon="x-mark" wire:click="$set('status', '')">
                            {{ __('Clear filters') }}</flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
            @if (ContentStatus::tryFrom($status))
                <x-admin.filter-chip :label="ContentStatus::from($status)->label()" wire:click="$set('status', '')" />
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

        @if ($this->bulkEnabled)
            <x-admin.bulk-bar>
                @can('pages.edit')
                    <flux:button
                        size="sm"
                        icon="eye"
                        iconVariant="outline"
                        wire:click="publishSelected"
                    >{{ __('Publish') }}</flux:button>
                    <flux:button
                        size="sm"
                        icon="eye-slash"
                        iconVariant="outline"
                        wire:click="unpublishSelected"
                    >{{ __('Unpublish') }}</flux:button>
                    @if ($this->categoryOptions->isNotEmpty())
                        <flux:modal.trigger name="bulk-category">
                            <flux:button
                                size="sm"
                                icon="tag"
                                iconVariant="outline"
                            >{{ __('Add to category') }}</flux:button>
                        </flux:modal.trigger>
                    @endif
                @endcan
                @can('pages.delete')
                    <flux:modal.trigger name="bulk-delete">
                        <flux:button
                            size="sm"
                            variant="danger"
                            icon="trash"
                            iconVariant="outline"
                        >{{ __('Delete') }}</flux:button>
                    </flux:modal.trigger>
                @endcan
            </x-admin.bulk-bar>
        @endif

        <flux:checkbox.group wire:model="selected">
            <flux:table
                class="max-h-[calc(100dvh-12rem)] md:w-full md:table-fixed"
                :paginate="$this->pages"
                container:class="max-h-[calc(100dvh-12rem)]"
            >
                <flux:table.columns sticky class="bg-white dark:bg-zinc-800">
                    @if ($this->bulkEnabled)
                        <flux:table.column class="w-8">
                            <flux:checkbox.all :aria-label="__('Select all')"
                        /></flux:table.column>
                    @endif
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'title'"
                        :direction="$sortDirection"
                        wire:click="sort('title')"
                    >
                        {{ __('Title') }}</flux:table.column>
                    @if ($this->hasMultipleActiveLocales())
                        <flux:table.column class="w-1/5">{{ __('Languages') }}</flux:table.column>
                    @endif
                    <flux:table.column class="w-1/6">{{ __('Status') }}</flux:table.column>
                    <flux:table.column
                        class="w-1/6"
                        sortable
                        :sorted="$sortBy === 'updated_at'"
                        :direction="$sortDirection"
                        wire:click="sort('updated_at')"
                    >
                        {{ __('Last updated') }}</flux:table.column>
                    <flux:table.column class="w-10"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->pages as $row)
                        <flux:table.row wire:key="{{ $row->id }}">
                            @if ($this->bulkEnabled)
                                <flux:table.cell>
                                    <flux:checkbox
                                        :value="$row->id"
                                        :aria-label="__('Select :title', ['title' => $row->title])"
                                    />
                                </flux:table.cell>
                            @endif
                            <flux:table.cell>
                                <div class="flex min-w-0 items-center justify-between gap-2">
                                    <a
                                        href="{{ route('admin.pages-edit', $row->id) }}"
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <flux:text
                                            variant="strong"
                                            class="truncate hover:underline"
                                        >{{ $row->title }}</flux:text>
                                    </a>
                                    @if ($row->id === $this->homePageId)
                                        <flux:badge size="sm" class="md:mr-6">{{ __('Homepage') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>

                            @if ($this->hasMultipleActiveLocales())
                                <flux:table.cell class="whitespace-nowrap">
                                    <x-admin.language-codes :active="$row->published_locales" />
                                </flux:table.cell>
                            @endif

                            <flux:table.cell>
                                <flux:badge color="{{ $row->computed_status->color() }}" size="sm">
                                    {{ $row->computed_status->label() }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell> {{ $row->updated_at?->format('M d, Y H:i') }} </flux:table.cell>

                            <flux:table.cell>
                                <flux:dropdown class="flex justify-end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" square />
                                    <flux:menu>
                                        <flux:menu.item icon="eye" href="{{ url($row->slug) }}" target="_blank">
                                            {{ __('View') }}
                                        </flux:menu.item>
                                        @can('pages.create')
                                            <flux:menu.item
                                                icon="document-duplicate"
                                                wire:click="duplicate({{ $row->id }})"
                                            >
                                                {{ __('Duplicate') }}
                                            </flux:menu.item>
                                        @endcan
                                        @can('pages.edit')
                                            <flux:menu.item
                                                icon="pencil"
                                                href="{{ route('admin.pages-edit', $row->id) }}"
                                            >
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                        @endcan

                                        @if ($row->id !== $this->configuredHomePageId && auth()->user()?->can('pages.delete'))
                                            <flux:menu.separator />
                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                wire:click="confirmDelete({{ $row->id }})"
                                            >
                                                {{ __('Delete') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:checkbox.group>
    </div>

    <flux:modal name="add-new" class="md:w-96">
        <flux:heading size="lg" class="mb-6">{{ __('Add a new page') }}</flux:heading>
        <form wire:submit="create" class="space-y-6">
            <flux:input wire:model="title" label="{{ __('Title') }}" badge="Required" autofocus />
            <div class="mt-6 flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="duplicate" class="md:w-96">
        <form wire:submit="confirmDuplicate" class="space-y-6">
            <flux:heading size="lg">{{ __('Duplicate page') }}</flux:heading>
            <flux:input wire:model="duplicateTitle" label="{{ __('Title') }}" autofocus />
            <div class="flex gap-3">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="document-duplicate"
                >{{ __('Duplicate') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    @can('pages.edit')
        <flux:modal name="bulk-category" class="md:w-96">
            <form wire:submit="addSelectedToCategory" class="space-y-6">
                <flux:heading size="lg">{{ __('Add to category') }}</flux:heading>
                <flux:select
                    wire:model="bulkCategoryId"
                    variant="listbox"
                    searchable
                    :label="__('Category')"
                    :placeholder="__('Choose a category…')"
                >
                    @foreach ($this->categoryOptions as $categoryOption)
                        <flux:select.option
                            :value="$categoryOption->id"
                            wire:key="bulk-category-{{ $categoryOption->id }}"
                        >
                            {{ $categoryOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    @can('pages.delete')
        <flux:modal name="bulk-delete" class="min-w-88">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="mb-6">{{ __('Delete the selected pages?') }}</flux:heading>
                    <flux:text>{{ __('This action cannot be reversed.') }}</flux:text>
                </div>
                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button>{{ __('No, Keep them.') }}</flux:button>
                    </flux:modal.close>
                    <flux:button
                        wire:click="deleteSelected"
                        variant="danger"
                    >{{ __('Yes, Delete them!') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan

    <flux:modal name="confirm-delete" class="min-w-88">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="mb-6">{{ __('Confirm delete') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to delete') }} "<span
                        class="text-black dark:text-white"
                        >{{ $this->pages->find($selectedId)?->title }}</span
                    >" ?
                </flux:text>
                <flux:text>{{ __('This action cannot be reversed.') }}</flux:text>
            </div>
            <div class="flex gap-3">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button>{{ __('No, Keep it.') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    wire:click="delete({{ $selectedId }})"
                    variant="danger"
                >{{ __('Yes, Delete it!') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@section('header-content')
    <flux:breadcrumbs>
        <flux:breadcrumbs.item class="pl-3 md:pl-0">{{ __('Pages') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
