<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonInterface;
use Flux\DateRange;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public ?DateRange $datesFilter = null;

    public function mount(): void
    {
        $this->datesFilter = new DateRange(now()->subDays(30)->startOfDay(), now()->endOfDay());
    }

    /**
     * @return array{start: CarbonInterface, end: CarbonInterface}
     */
    private function period(): array
    {
        $start = $this->datesFilter?->getStartDate() ?? now()->subCentury();
        $end = $this->datesFilter?->getEndDate() ?? now();

        return ['start' => $start->copy()->startOfDay(), 'end' => $end->copy()->endOfDay()];
    }

    /**
     * @return array<int, array{key: string, name: string, icon: string, total: int, published: int}>
     */
    #[Computed]
    public function recordTypeStats(): array
    {
        return RecordType::query()
            ->orderBy('position')
            ->get()
            ->map(fn (RecordType $type): array => [
                'key' => $type->key,
                'name' => $type->name,
                'icon' => $type->icon,
                'total' => $type->records()->count(),
                'published' => $type->records()->published()->count(),
            ])
            ->all();
    }

    #[Computed]
    public function publishedContentCount(): int
    {
        return Record::query()->published()->count() + Page::query()->published()->count();
    }

    /**
     * @return array{count: int, bytes: int}
     */
    #[Computed]
    public function mediaStats(): array
    {
        return [
            'count' => Media::query()->count(),
            'bytes' => (int) Media::query()->sum('size'),
        ];
    }

    #[Computed]
    public function unreadMessages(): int
    {
        return Submission::query()->unread()->count();
    }

    /**
     * @return Collection<int, Submission>
     */
    #[Computed]
    public function latestMessages(): Collection
    {
        return Submission::query()->latest()->limit(5)->get();
    }

    #[Computed]
    public function userCount(): int
    {
        return User::query()->count();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function onlineUsers(): Collection
    {
        return User::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes(15))
            ->latest('last_seen_at')
            ->limit(8)
            ->get();
    }

    /**
     * @return array<int, array{title: string, type: string, updated: CarbonInterface, url: string, status: \App\Enums\ContentStatus, editor: string|null}>
     */
    #[Computed]
    public function recentActivity(): array
    {
        $records = Record::query()
            ->with(['recordType', 'editor'])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Record $record): array => [
                'title' => $record->title !== '' ? $record->title : (string) __('Untitled'),
                'type' => $record->recordType->name,
                'updated' => $record->updated_at,
                'url' => route('admin.records-edit', [$record->recordType, $record]),
                'status' => $record->computed_status,
                'editor' => $record->editor?->name,
            ])
            ->all();

        $pages = Page::query()
            ->with('editor')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Page $page): array => [
                'title' => $page->title !== '' ? $page->title : (string) __('Untitled'),
                'type' => (string) __('Page'),
                'updated' => $page->updated_at,
                'url' => route('admin.pages-edit', $page),
                'status' => $page->computed_status,
                'editor' => $page->editor?->name,
            ])
            ->all();

        $items = [...$records, ...$pages];

        usort($items, fn (array $a, array $b): int => $b['updated'] <=> $a['updated']);

        return array_slice($items, 0, 8);
    }

    #[Computed]
    public function hasMultipleStaff(): bool
    {
        $staffRoleIds = Role::query()->get()
            ->filter(fn (Role $role): bool => $role->canAccessAdmin())
            ->pluck('id');

        return User::query()->whereIn('role_id', $staffRoleIds)->count() > 1;
    }

    /**
     * @return array{records: int, pages: int, messages: int, users: int}
     */
    #[Computed]
    public function newInPeriod(): array
    {
        ['start' => $start, 'end' => $end] = $this->period();

        return [
            'records' => Record::query()->whereBetween('created_at', [$start, $end])->count(),
            'pages' => Page::query()->whereBetween('created_at', [$start, $end])->count(),
            'messages' => Submission::query()->whereBetween('created_at', [$start, $end])->count(),
            'users' => User::query()->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Dashboard'))
            ->layout('layouts::admin');
    }
};
?>

<div class="space-y-6 md:space-y-8">
    @php
        $media = $this->mediaStats();
        $new = $this->newInPeriod();
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:subheading>{{ __("Here's what's happening across your site.") }}</flux:subheading>
        </div>
        <div class="w-full sm:w-64">
            <flux:date-picker mode="range" wire:model.live="datesFilter" presets="last7Days last30Days last3Months last6Months allTime" :max="now()->format('Y-m-d')" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex items-center gap-4">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                <flux:icon name="document-check" class="size-6" />
            </div>
            <div class="min-w-0">
                <flux:heading size="xl" class="tabular-nums">{{ number_format($this->publishedContentCount) }}</flux:heading>
                <flux:subheading>{{ __('Published items') }}</flux:subheading>
            </div>
        </flux:card>

        @can('inbox.view')
            <a href="{{ route('admin.inbox-index') }}" wire:navigate class="block">
                <flux:card class="flex h-full items-center gap-4 transition hover:shadow-md">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <flux:icon name="envelope" class="size-6" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="xl" class="tabular-nums">{{ number_format($this->unreadMessages) }}</flux:heading>
                        <flux:subheading>{{ __('Unread messages') }}</flux:subheading>
                    </div>
                </flux:card>
            </a>
        @endcan

        <flux:card class="flex items-center gap-4">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
                <flux:icon name="photo" class="size-6" />
            </div>
            <div class="min-w-0">
                <flux:heading size="xl" class="tabular-nums">{{ \Illuminate\Support\Number::fileSize($media['bytes'], precision: 1) }}</flux:heading>
                <flux:subheading>{{ trans_choice(':count file|:count files', $media['count'], ['count' => number_format($media['count'])]) }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="users" class="size-6" />
            </div>
            <div class="min-w-0">
                <flux:heading size="xl" class="tabular-nums">{{ number_format($this->onlineUsers->count()) }}</flux:heading>
                <flux:subheading>{{ __('Online now') }} · {{ __(':count total', ['count' => number_format($this->userCount)]) }}</flux:subheading>
            </div>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Content') }}</flux:heading>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($this->recordTypeStats as $stat)
                        @can('records.'.$stat['key'].'.view')
                            <a href="{{ route('admin.records-index', $stat['key']) }}" wire:navigate wire:key="stat-{{ $stat['key'] }}"
                               class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:bg-zinc-50 dark:border-white/10 dark:hover:bg-white/5">
                                <flux:icon :name="$stat['icon']" class="size-5 shrink-0 text-zinc-400" />
                                <div class="min-w-0 flex-1">
                                    <flux:heading size="sm" class="truncate">{{ $stat['name'] }}</flux:heading>
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                        {{ __(':published live', ['published' => number_format($stat['published'])]) }}
                                        · {{ __(':total total', ['total' => number_format($stat['total'])]) }}
                                    </flux:text>
                                </div>
                            </a>
                        @endcan
                    @endforeach

                    @can('pages.view')
                        <a href="{{ route('admin.pages-index') }}" wire:navigate
                           class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:bg-zinc-50 dark:border-white/10 dark:hover:bg-white/5">
                            <flux:icon name="document" class="size-5 shrink-0 text-zinc-400" />
                            <div class="min-w-0 flex-1">
                                <flux:heading size="sm" class="truncate">{{ __('Pages') }}</flux:heading>
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __(':count total', ['count' => number_format(\App\Models\Page::query()->count())]) }}</flux:text>
                            </div>
                        </a>
                    @endcan
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Recent activity') }}</flux:heading>
                @if (empty($this->recentActivity))
                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Nothing edited yet.') }}</flux:text>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-white/5">
                        @foreach ($this->recentActivity as $item)
                            <a href="{{ $item['url'] }}" wire:navigate wire:key="activity-{{ $loop->index }}" class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                                <div class="min-w-0 flex-1">
                                    <flux:heading size="sm" class="truncate">{{ $item['title'] }}</flux:heading>
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                        {{ $item['type'] }} · {{ $item['updated']?->diffForHumans() }}@if ($this->hasMultipleStaff && $item['editor']) · {{ __('by :name', ['name' => $item['editor']]) }}@endif
                                    </flux:text>
                                </div>
                                <flux:badge size="sm" :color="$item['status']->color()">{{ $item['status']->label() }}</flux:badge>
                            </a>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>

        <div class="space-y-6">
            @can('inbox.view')
                <flux:card class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ __('Latest messages') }}</flux:heading>
                        <flux:button size="sm" variant="ghost" :href="route('admin.inbox-index')" wire:navigate>{{ __('View all') }}</flux:button>
                    </div>
                    @if ($this->latestMessages->isEmpty())
                        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No messages yet.') }}</flux:text>
                    @else
                        <div class="divide-y divide-zinc-100 dark:divide-white/5">
                            @foreach ($this->latestMessages as $message)
                                <a href="{{ route('admin.inbox-show', $message) }}" wire:navigate wire:key="msg-{{ $message->id }}" class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0">
                                    <span @class([
                                        'mt-1.5 size-2 shrink-0 rounded-full',
                                        'bg-sky-500' => $message->read_at === null,
                                        'bg-transparent' => $message->read_at !== null,
                                    ])></span>
                                    <div class="min-w-0 flex-1">
                                        <flux:heading size="sm" class="truncate">{{ $message->name ?: ($message->email ?: __('Anonymous')) }}</flux:heading>
                                        <flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400">{{ $message->subject ?: \Illuminate\Support\Str::limit(strip_tags((string) $message->message), 40) }}</flux:text>
                                    </div>
                                    <flux:text size="sm" class="shrink-0 text-zinc-400">{{ $message->created_at->diffForHumans(short: true) }}</flux:text>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            @endcan

            @can('users.view')
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('Online now') }}</flux:heading>
                    @if ($this->onlineUsers->isEmpty())
                        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No one else is online.') }}</flux:text>
                    @else
                        <div class="flex flex-col gap-3">
                            @foreach ($this->onlineUsers as $user)
                                <div class="flex items-center gap-3" wire:key="online-{{ $user->id }}">
                                    <flux:avatar size="sm" :name="$user->name" :src="$user->photo_url" />
                                    <div class="min-w-0 flex-1">
                                        <flux:heading size="sm" class="truncate">{{ $user->name }}</flux:heading>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $user->last_seen_at?->diffForHumans() }}</flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            @endcan

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('New this period') }}</flux:heading>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <flux:heading size="lg" class="tabular-nums">{{ number_format($new['records']) }}</flux:heading>
                        <flux:subheading>{{ __('Records') }}</flux:subheading>
                    </div>
                    <div>
                        <flux:heading size="lg" class="tabular-nums">{{ number_format($new['pages']) }}</flux:heading>
                        <flux:subheading>{{ __('Pages') }}</flux:subheading>
                    </div>
                    <div>
                        <flux:heading size="lg" class="tabular-nums">{{ number_format($new['messages']) }}</flux:heading>
                        <flux:subheading>{{ __('Messages') }}</flux:subheading>
                    </div>
                    <div>
                        <flux:heading size="lg" class="tabular-nums">{{ number_format($new['users']) }}</flux:heading>
                        <flux:subheading>{{ __('Users') }}</flux:subheading>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>

@section('header-content')
    <flux:breadcrumbs>
        <flux:breadcrumbs.item class="pl-3 md:pl-0">{{ __('Dashboard') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
