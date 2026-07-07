<?php

declare(strict_types=1);

use App\Services\GoogleAnalyticsDataService;
use Carbon\CarbonInterface;
use Flux\DateRange;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public ?DateRange $datesFilter = null;

    public function mount(GoogleAnalyticsDataService $analytics): void
    {
        abort_unless($analytics->configured(), 404);

        $this->datesFilter = new DateRange(now()->subDays(30)->startOfDay(), now()->endOfDay());
    }

    /**
     * @return array{start: CarbonInterface, end: CarbonInterface}
     */
    private function period(): array
    {
        $start = $this->datesFilter?->getStartDate() ?? now()->subDays(30);
        $end = $this->datesFilter?->getEndDate() ?? now();

        return ['start' => $start->copy()->startOfDay(), 'end' => $end->copy()->endOfDay()];
    }

    /**
     * @return array{
     *     totals: array{activeUsers: int, newUsers: int, sessions: int, pageViews: int},
     *     timeseries: array<int, array{date: string, users: int}>,
     *     countries: array<int, array{country: string, users: int}>,
     *     pages: array<int, array{path: string, title: string, views: int}>,
     * }|null
     */
    #[Computed]
    public function report(): ?array
    {
        ['start' => $start, 'end' => $end] = $this->period();
        $analytics = resolve(GoogleAnalyticsDataService::class);

        try {
            return [
                'totals' => $analytics->totals($start, $end),
                'timeseries' => $analytics->usersOverTime($start, $end),
                'countries' => $analytics->topCountries($start, $end),
                'pages' => $analytics->topPages($start, $end),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Analytics'))
            ->layout('layouts::admin');
    }
};
?>

<div class="space-y-6 md:space-y-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Analytics') }}</flux:heading>
            <flux:subheading>{{ __('Visitor stats from Google Analytics.') }}</flux:subheading>
        </div>
        <div class="flex w-full items-center gap-3 sm:w-auto">
            <flux:icon.loading wire:loading wire:target="datesFilter" class="size-5 shrink-0 text-zinc-400" />
            <div class="ml-4 w-full sm:w-64">
                <flux:date-picker mode="range" wire:model.live="datesFilter" presets="today yesterday thisWeek last7Days thisMonth last30Days last3Months last6Months yearToDate" :max="now()->format('Y-m-d')" />
            </div>
        </div>
    </div>

    @if ($this->report === null)
        <flux:callout icon="exclamation-triangle" variant="warning">
            <flux:callout.heading>{{ __('Analytics unavailable') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Google Analytics did not respond. Check the connection in Settings → Integrations.') }}</flux:callout.text>
        </flux:callout>
    @else
        @php($totals = $this->report['totals'])

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card class="flex items-center gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                    <flux:icon name="users" class="size-6" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="xl" class="tabular-nums">{{ number_format($totals['activeUsers']) }}</flux:heading>
                    <flux:subheading>{{ __('Active users') }}</flux:subheading>
                </div>
            </flux:card>

            <flux:card class="flex items-center gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="user-plus" class="size-6" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="xl" class="tabular-nums">{{ number_format($totals['newUsers']) }}</flux:heading>
                    <flux:subheading>{{ __('New users') }}</flux:subheading>
                </div>
            </flux:card>

            <flux:card class="flex items-center gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
                    <flux:icon name="cursor-arrow-rays" class="size-6" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="xl" class="tabular-nums">{{ number_format($totals['sessions']) }}</flux:heading>
                    <flux:subheading>{{ __('Sessions') }}</flux:subheading>
                </div>
            </flux:card>

            <flux:card class="flex items-center gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <flux:icon name="eye" class="size-6" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="xl" class="tabular-nums">{{ number_format($totals['pageViews']) }}</flux:heading>
                    <flux:subheading>{{ __('Page views') }}</flux:subheading>
                </div>
            </flux:card>
        </div>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Users over time') }}</flux:heading>
            @if ($this->report['timeseries'] === [])
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No data for this period.') }}</flux:text>
            @else
                <flux:chart :value="$this->report['timeseries']" class="aspect-[3/1]">
                    <flux:chart.svg>
                        <flux:chart.line field="users" class="text-sky-500" curve="smooth" />
                        <flux:chart.area field="users" class="text-sky-500/15" curve="smooth" />
                        <flux:chart.axis axis="x" field="date">
                            <flux:chart.axis.line />
                            <flux:chart.axis.tick />
                        </flux:chart.axis>
                        <flux:chart.axis axis="y">
                            <flux:chart.axis.grid />
                            <flux:chart.axis.tick />
                        </flux:chart.axis>
                        <flux:chart.cursor />
                    </flux:chart.svg>
                    <flux:chart.tooltip>
                        <flux:chart.tooltip.heading field="date" :format="['dateStyle' => 'medium']" />
                        <flux:chart.tooltip.value field="users" :label="__('Users')" />
                    </flux:chart.tooltip>
                </flux:chart>
            @endif
        </flux:card>

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Top countries') }}</flux:heading>
                @if ($this->report['countries'] === [])
                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No data for this period.') }}</flux:text>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-white/10">
                        @foreach ($this->report['countries'] as $row)
                            <div wire:key="country-{{ $loop->index }}" class="flex items-center justify-between gap-3 py-2.5">
                                <flux:text class="truncate">{{ $row['country'] }}</flux:text>
                                <flux:text class="font-medium tabular-nums">{{ number_format($row['users']) }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Top pages') }}</flux:heading>
                @if ($this->report['pages'] === [])
                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No data for this period.') }}</flux:text>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-white/10">
                        @foreach ($this->report['pages'] as $row)
                            <div wire:key="page-{{ $loop->index }}" class="flex items-center justify-between gap-3 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <flux:heading size="sm" class="truncate">{{ $row['title'] }}</flux:heading>
                                    <flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400">{{ $row['path'] }}</flux:text>
                                </div>
                                <flux:text class="font-medium tabular-nums">{{ number_format($row['views']) }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>
    @endif
</div>

@section('header-content')
    <flux:breadcrumbs>
        <flux:breadcrumbs.item class="pl-3 md:pl-0">{{ __('Analytics') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
