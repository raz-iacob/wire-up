<?php

declare(strict_types=1);

use App\Actions\ImportRedirectsAction;
use App\Actions\SaveRedirectAction;
use App\Enums\ActivityEvent;
use App\Enums\RedirectOrigin;
use App\Models\NotFoundPath;
use App\Models\Redirect;
use App\Services\ActivityLog;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

return new class extends Component
{
    use WithFileUploads, WithPagination;

    private const int MAX_IMPORT_ROWS = 5000;

    public string $tab = 'redirects';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public ?int $editingId = null;

    public string $from = '';

    public string $to = '';

    public int $statusCode = 301;

    public ?int $deletingId = null;

    public ?TemporaryUploadedFile $csv = null;

    /**
     * @var array{added: int, updated: int, skipped: array<int, array{row: int, from: string, reason: string}>}|null
     */
    public ?array $importSummary = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Redirect>
     */
    #[Computed]
    public function redirects(): LengthAwarePaginator
    {
        return Redirect::query()
            ->when($this->search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query->whereLike('from', "%{$this->search}%")->orWhereLike('to', "%{$this->search}%"),
            ))
            ->latest('id')
            ->paginate(25);
    }

    /**
     * @return LengthAwarePaginator<int, NotFoundPath>
     */
    #[Computed]
    public function notFound(): LengthAwarePaginator
    {
        return NotFoundPath::query()
            ->orderByDesc('hits')
            ->latest('last_seen_at')
            ->paginate(25, pageName: 'notFoundPage');
    }

    public function add(): void
    {
        $this->authorize('settings.edit');

        $this->openForm(null, '', '', 301);
    }

    public function redirectNotFound(int $id): void
    {
        $this->authorize('settings.edit');

        $this->openForm(null, NotFoundPath::query()->findOrFail($id)->path, '', 301);
    }

    public function edit(int $id): void
    {
        $this->authorize('settings.edit');

        $redirect = Redirect::query()->findOrFail($id);

        $this->openForm($redirect->id, $redirect->from, $redirect->to, $redirect->status_code);
    }

    public function save(SaveRedirectAction $action): void
    {
        $this->authorize('settings.edit');

        $this->validate([
            'from' => ['required', 'string', 'max:500'],
            'to' => ['required', 'string', 'max:2048'],
            'statusCode' => ['required', 'integer', 'in:301,302'],
        ], [], [
            'from' => __('old address'),
            'to' => __('new address'),
            'statusCode' => __('type'),
        ]);

        $redirect = $this->editingId === null ? null : Redirect::query()->findOrFail($this->editingId);

        ActivityLog::record(ActivityEvent::REDIRECT_SAVED, $action->handle($this->from, $this->to, $this->statusCode, RedirectOrigin::MANUAL, $redirect));

        $this->reset('editingId', 'from', 'to', 'statusCode');
        unset($this->redirects, $this->notFound);

        Flux::modal('redirect-form')->close();
        Flux::toast(__('Redirect saved.'), variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('settings.edit');

        $this->deletingId = $id;

        Flux::modal('delete-redirect')->show();
    }

    public function delete(): void
    {
        $this->authorize('settings.edit');

        Redirect::query()->find($this->deletingId)?->delete();

        $this->deletingId = null;
        unset($this->redirects);

        Flux::modal('delete-redirect')->close();
        Flux::toast(__('Redirect deleted.'), variant: 'success');
    }

    public function dismiss(int $id): void
    {
        $this->authorize('settings.edit');

        NotFoundPath::query()->whereKey($id)->delete();

        unset($this->notFound);
    }

    public function import(ImportRedirectsAction $action): void
    {
        $this->authorize('settings.edit');

        $this->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [], ['csv' => __('CSV file')]);

        $rows = $action->csvRows((string) $this->csv?->getRealPath());

        if (count($rows) > self::MAX_IMPORT_ROWS) {
            $this->addError('csv', __('Import up to :count redirects at a time.', ['count' => number_format(self::MAX_IMPORT_ROWS)]));

            return;
        }

        $this->importSummary = $action->handle($rows, RedirectOrigin::IMPORT);
        $this->csv = null;
        unset($this->redirects, $this->notFound);

        Flux::modal('import-redirects')->close();
    }

    public function dismissSummary(): void
    {
        $this->importSummary = null;
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Redirects'))
            ->layout('layouts::admin');
    }

    private function openForm(?int $id, string $from, string $to, int $statusCode): void
    {
        $this->resetValidation();

        $this->editingId = $id;
        $this->from = $from;
        $this->to = $to;
        $this->statusCode = $statusCode;

        Flux::modal('redirect-form')->show();
    }
};
?>

<x-admin.settings-layout>
    <flux:tab.group>
        <flux:tabs wire:model="tab">
            <flux:tab name="redirects">{{ __('Redirects') }}</flux:tab>
            <flux:tab name="not-found">{{ __('Not found') }}</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="redirects">
            <div class="space-y-6">
                <div class="flex flex-wrap items-center gap-3">
                    @can('settings.edit')
                        <flux:button
                            variant="primary"
                            size="sm"
                            icon="plus"
                            iconVariant="outline"
                            wire:click="add"
                        >{{ __('Add') }}</flux:button>
                        <flux:modal.trigger name="import-redirects">
                            <flux:button size="sm" icon="arrow-up-tray" iconVariant="outline">
                                {{ __('Import CSV') }}</flux:button>
                        </flux:modal.trigger>
                    @endcan

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

                @if ($importSummary !== null)
                    <flux:callout
                        :icon="$importSummary['skipped'] === [] ? 'check-circle' : 'exclamation-triangle'"
                        :variant="$importSummary['skipped'] === [] ? 'success' : 'warning'"
                    >
                        <flux:callout.heading>
                            {{ __(':added added, :updated updated, :skipped skipped.', ['added' => $importSummary['added'], 'updated' => $importSummary['updated'], 'skipped' => count($importSummary['skipped'])]) }}</flux:callout.heading>
                        <flux:callout.text>
                            @foreach (array_slice($importSummary['skipped'], 0, 10) as $skipped)
                                <div>
                                    {{ __('Line :line', ['line' => $skipped['row']]) }}: {{ $skipped['from'] !== '' ? $skipped['from'].' — ' : '' }}{{ $skipped['reason'] }}
                                </div>
                            @endforeach
                            <div class="mt-3">
                                <flux:button size="sm" variant="ghost" wire:click="dismissSummary">
                                    {{ __('Dismiss') }}</flux:button>
                            </div>
                        </flux:callout.text>
                    </flux:callout>
                @endif

                @if ($this->redirects->isEmpty())
                    <flux:text variant="subtle">
                        {{ $search === '' ? __('No redirects yet. Changing the web address of a published page adds one automatically.') : __('No redirects match your search.') }}</flux:text>
                @else
                    <flux:table :paginate="$this->redirects" class="md:table-fixed">
                        <flux:table.columns>
                            <flux:table.column>{{ __('From') }}</flux:table.column>
                            <flux:table.column>{{ __('To') }}</flux:table.column>
                            <flux:table.column class="w-28">{{ __('Type') }}</flux:table.column>
                            <flux:table.column class="w-20" align="end">{{ __('Hits') }}</flux:table.column>
                            <flux:table.column class="w-40">{{ __('Last used') }}</flux:table.column>
                            <flux:table.column class="w-10"></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->redirects as $row)
                                <flux:table.row wire:key="redirect-{{ $row->id }}">
                                    <flux:table.cell>
                                        <div class="flex min-w-0 items-center gap-2">
                                            <flux:text variant="strong" class="truncate" title="{{ $row->from }}">
                                                {{ $row->from }}</flux:text>
                                            @if ($row->origin !== RedirectOrigin::MANUAL)
                                                <flux:badge size="sm" color="zinc" class="shrink-0">
                                                    {{ $row->origin->label() }}</flux:badge>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:text class="truncate" title="{{ $row->to }}">{{ $row->to }}</flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$row->status_code === 301 ? 'sky' : 'amber'">
                                            {{ $row->status_code === 301 ? __('Permanent') : __('Temporary') }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">
                                        {{ number_format($row->hits) }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap">
                                        {{ $row->last_hit_at?->format('M d, Y H:i') ?? __('Never') }}</flux:table.cell>
                                    <flux:table.cell>
                                        @can('settings.edit')
                                            <flux:dropdown class="flex justify-end">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="ellipsis-horizontal"
                                                    square
                                                />
                                                <flux:menu>
                                                    <flux:menu.item icon="pencil" wire:click="edit({{ $row->id }})">
                                                        {{ __('Edit') }}</flux:menu.item>
                                                    <flux:menu.separator />
                                                    <flux:menu.item
                                                        icon="trash"
                                                        variant="danger"
                                                        wire:click="confirmDelete({{ $row->id }})"
                                                    >
                                                        {{ __('Delete') }}</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endcan
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="not-found">
            <div class="space-y-6">
                <flux:text>{{ __('Addresses visitors asked for that do not exist, most requested first.') }}</flux:text>

                @if ($this->notFound->isEmpty())
                    <flux:text variant="subtle">{{ __('Nothing yet.') }}</flux:text>
                @else
                    <flux:table :paginate="$this->notFound" class="md:table-fixed">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Address') }}</flux:table.column>
                            <flux:table.column class="w-20" align="end">{{ __('Hits') }}</flux:table.column>
                            <flux:table.column class="w-40">{{ __('Last seen') }}</flux:table.column>
                            <flux:table.column class="w-10"></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->notFound as $row)
                                <flux:table.row wire:key="not-found-{{ $row->id }}">
                                    <flux:table.cell>
                                        <flux:text variant="strong" class="truncate" title="{{ $row->path }}">
                                            {{ $row->path }}</flux:text>
                                        @if ($row->referrer !== null)
                                            <flux:text size="sm" class="truncate" title="{{ $row->referrer }}">
                                                {{ __('From :referrer', ['referrer' => $row->referrer]) }}</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">
                                        {{ number_format($row->hits) }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap">
                                        {{ $row->last_seen_at->format('M d, Y H:i') }}</flux:table.cell>
                                    <flux:table.cell>
                                        @can('settings.edit')
                                            <flux:dropdown class="flex justify-end">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="ellipsis-horizontal"
                                                    square
                                                />
                                                <flux:menu>
                                                    <flux:menu.item
                                                        icon="arrow-uturn-right"
                                                        wire:click="redirectNotFound({{ $row->id }})"
                                                    >
                                                        {{ __('Redirect') }}</flux:menu.item>
                                                    <flux:menu.item icon="x-mark" wire:click="dismiss({{ $row->id }})">
                                                        {{ __('Dismiss') }}</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endcan
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </flux:tab.panel>
    </flux:tab.group>

    @can('settings.edit')
        <flux:modal name="redirect-form" class="md:w-[28rem]">
            <flux:heading size="lg" class="mb-6">
                {{ $editingId === null ? __('Add a redirect') : __('Edit redirect') }}</flux:heading>
            <form wire:submit="save" class="space-y-6">
                <flux:input wire:model="from" :label="__('Old address')" placeholder="/old-page" autofocus />
                <flux:input
                    wire:model="to"
                    :label="__('New address')"
                    placeholder="/new-page"
                    :description="__('A path on this site or a full web address. End both with /* to move a whole folder.')"
                />
                <flux:select wire:model="statusCode" :label="__('Type')">
                    <flux:select.option value="301">{{ __('Permanent (301)') }}</flux:select.option>
                    <flux:select.option value="302">{{ __('Temporary (302)') }}</flux:select.option>
                </flux:select>
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="import-redirects" class="md:w-[28rem]">
            <flux:heading size="lg" class="mb-2">{{ __('Import redirects') }}</flux:heading>
            <flux:text class="mb-6">
                {{ __('A CSV with the old address, the new address and optionally 301 or 302 on each line.') }}</flux:text>
            <form wire:submit="import" class="space-y-6">
                <flux:field>
                    <flux:input type="file" wire:model="csv" accept=".csv,text/csv" />
                    <flux:error name="csv" />
                </flux:field>
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Import') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="delete-redirect" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete redirect') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Visitors following the old address will see the not found page instead.') }}</flux:text>
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
    @endcan
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('Redirects') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Redirects') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
