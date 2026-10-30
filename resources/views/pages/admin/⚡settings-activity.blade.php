<?php

declare(strict_types=1);

use App\Enums\ActivityArea;
use App\Models\Activity;
use App\Models\Record;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $area = '';

    #[Url(except: '')]
    public string $person = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'area', 'person'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        $area = ActivityArea::tryFrom($this->area);

        return Activity::query()
            ->with(['user.role', 'impersonator.role', 'subject' => fn (MorphTo $subject): MorphTo => $subject->morphWith([Record::class => ['recordType']])])
            ->when($area instanceof ActivityArea, fn (Builder $query): Builder => $query->whereIn('event', $area?->events() ?? []))
            ->when($this->person !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query->where('user_id', (int) $this->person)->orWhere('impersonator_id', (int) $this->person),
            ))
            ->when($this->search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->whereLike('subject_label', "%{$this->search}%")
                    ->orWhereHas('user', fn (Builder $user): Builder => $user->visibleToViewer()->whereLike('name', "%{$this->search}%")),
            ))
            ->latest('id')
            ->paginate(50);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function staff(): Collection
    {
        $staffRoleIds = Role::query()->get()
            ->filter(fn (Role $role): bool => $role->canAccessAdmin())
            ->pluck('id');

        return User::query()->visibleToViewer()->whereIn('role_id', $staffRoleIds)->orderBy('name')->get();
    }

    public function clearFilters(): void
    {
        $this->reset('area', 'person');
        $this->resetPage();
    }

    #[Computed]
    public function personName(): ?string
    {
        return $this->staff()->firstWhere('id', (int) $this->person)?->name;
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Activity log'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.settings-layout>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <flux:dropdown position="bottom" align="start">
                <flux:button
                    class="shrink-0"
                    size="sm"
                    icon="funnel"
                    iconVariant="outline"
                >{{ __('Filter') }}</flux:button>

                <flux:menu>
                    <flux:menu.radio.group wire:model.live="area" heading="{{ __('Area') }}">
                        <flux:menu.radio value="" checked>{{ __('All') }}</flux:menu.radio>
                        @foreach (ActivityArea::cases() as $option)
                            <flux:menu.radio :value="$option->value">{{ $option->label() }}</flux:menu.radio>
                        @endforeach
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <flux:menu.radio.group wire:model.live="person" heading="{{ __('Person') }}">
                        <flux:menu.radio value="" checked>{{ __('Anyone') }}</flux:menu.radio>
                        @foreach ($this->staff as $member)
                            <flux:menu.radio :value="(string) $member->id">{{ $member->name }}</flux:menu.radio>
                        @endforeach
                    </flux:menu.radio.group>
                    @if ($area !== '' || $person !== '')
                        <flux:menu.separator />
                        <flux:menu.item icon="x-mark" wire:click="clearFilters">
                            {{ __('Clear filters') }}</flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
            @if (ActivityArea::tryFrom($area) !== null)
                <x-admin.filter-chip :label="ActivityArea::from($area)->label()" wire:click="$set('area', '')" />
            @endif
            @if ($this->personName !== null)
                <x-admin.filter-chip :label="$this->personName" wire:click="$set('person', '')" />
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

        @if ($this->activities->isEmpty())
            <flux:text variant="subtle">
                {{ $search === '' && $area === '' && $person === '' ? __('Nothing yet.') : __('No activity matches these filters.') }}</flux:text>
        @else
            <flux:table :paginate="$this->activities" class="md:table-fixed">
                <flux:table.columns>
                    <flux:table.column class="w-40">{{ __('When') }}</flux:table.column>
                    <flux:table.column class="w-48">{{ __('Who') }}</flux:table.column>
                    <flux:table.column>{{ __('What') }}</flux:table.column>
                    <flux:table.column>{{ __('Subject') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->activities as $activity)
                        <flux:table.row wire:key="activity-{{ $activity->id }}">
                            <flux:table.cell class="whitespace-nowrap">
                                <div>{{ $activity->created_at->format('M d, Y H:i') }}</div>
                                @if ($activity->ip !== null)
                                    <flux:text size="sm" class="tabular-nums">{{ $activity->ip }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text variant="strong" class="truncate">{{ $activity->actorName() }}</flux:text>
                                @if ($activity->actingAs() !== null)
                                    <flux:text size="sm" class="truncate">
                                        {{ __('as :name', ['name' => $activity->actingAs()]) }}</flux:text>
                                @endif
                                @if ($activity->viaAssistant())
                                    <flux:badge size="sm" color="violet">{{ __('AI assistant') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex min-w-0 items-start gap-2">
                                    <flux:icon
                                        :name="$activity->event->area()->icon()"
                                        variant="micro"
                                        class="mt-0.5 shrink-0 text-zinc-400"
                                    />
                                    <div class="min-w-0">
                                        <div>{{ $activity->event->label() }}</div>
                                        @if ($activity->details() !== null)
                                            <flux:text size="sm" class="truncate" title="{{ $activity->details() }}">
                                                {{ $activity->details() }}</flux:text>
                                        @endif
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($activity->subjectUrl() !== null)
                                    <flux:link
                                        :href="$activity->subjectUrl()"
                                        wire:navigate
                                        class="block truncate"
                                    >{{ $activity->subject_label }}</flux:link>
                                @else
                                    <flux:text class="truncate" title="{{ $activity->subject_label }}">
                                        {{ $activity->subject_label ?? '—' }}</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        <flux:text size="sm">{{ __('Entries are kept for :days days.', ['days' => Activity::DAYS_KEPT]) }}</flux:text>
    </div>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('Activity log') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Activity log') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
