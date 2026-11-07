<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Enums\ActivityEvent;
use App\Jobs\CopyBackupOffsite;
use App\Jobs\RunBackup;
use App\Services\ActivityLog;
use App\Services\BackupService;
use App\Services\OffsiteBackupService;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public bool $enabled = false;

    public string $frequency = 'daily';

    public int $keep = 5;

    public bool $includeFiles = true;

    public int $offsiteKeep = 14;

    public ?string $deleteFile = null;

    public function mount(): void
    {
        $settings = SettingsService::current();

        $this->enabled = $settings->backupsEnabled();
        $this->frequency = $settings->backupFrequency();
        $this->keep = $settings->backupsToKeep();
        $this->includeFiles = $settings->backupIncludesFiles();
        $this->offsiteKeep = $settings->offsiteBackupsToKeep();
    }

    #[Computed]
    public function offsiteConnected(): bool
    {
        return resolve(OffsiteBackupService::class)->configured();
    }

    /**
     * @return list<array{file: string, kind: string, bytes: int, created_at: CarbonImmutable}>|null
     */
    #[Computed]
    public function offsiteFiles(): ?array
    {
        return resolve(OffsiteBackupService::class)->files();
    }

    /**
     * @return list<array{file: string, kind: string, bytes: int, created_at: CarbonImmutable, local: bool, offsite: bool}>
     */
    #[Computed]
    public function rows(): array
    {
        $offsite = [];

        foreach ($this->offsiteFiles() ?? [] as $backup) {
            $offsite[$backup['file']] = $backup;
        }

        $rows = [];

        foreach (resolve(BackupService::class)->all() as $backup) {
            $rows[] = [...$backup, 'local' => true, 'offsite' => isset($offsite[$backup['file']])];
            unset($offsite[$backup['file']]);
        }

        foreach ($offsite as $backup) {
            $rows[] = [...$backup, 'local' => false, 'offsite' => true];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($b['created_at']->toIso8601String().$b['file'], $a['created_at']->toIso8601String().$a['file']));

        return $rows;
    }

    /**
     * @return array{status: string, file: ?string, output: ?string}|null
     */
    public function offsiteState(): ?array
    {
        return resolve(OffsiteBackupService::class)->state();
    }

    public function retryOffsite(): void
    {
        $this->authorize('settings.edit');

        $offsite = resolve(OffsiteBackupService::class);
        $file = $offsite->state()['file'] ?? null;

        if ($file === null) {
            return;
        }

        $offsite->markCopying($file);

        dispatch(new CopyBackupOffsite($file));
    }

    public function dismissOffsite(): void
    {
        $this->authorize('settings.edit');

        resolve(OffsiteBackupService::class)->clearState();
    }

    /**
     * @return array<int, array{file: string, kind: string, bytes: int, created_at: CarbonImmutable}>
     */
    #[Computed]
    public function backups(): array
    {
        return resolve(BackupService::class)->all();
    }

    /**
     * @return array{status: string, file: ?string, output: ?string, at: ?CarbonImmutable}
     */
    public function state(): array
    {
        return resolve(BackupService::class)->state();
    }

    public function diskSummary(): string
    {
        $backups = resolve(BackupService::class);
        $free = $backups->freeBytes();
        $used = Number::fileSize($backups->totalBytes(), precision: 1);

        return $free === null
            ? __('Backups use :used.', ['used' => $used])
            : __('Backups use :used, and :free is free on this server.', ['used' => $used, 'free' => Number::fileSize($free, precision: 1)]);
    }

    public function update(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validate([
            'enabled' => ['boolean'],
            'frequency' => ['required', 'in:daily,weekly'],
            'keep' => ['required', 'integer', 'min:1', 'max:30'],
            'includeFiles' => ['boolean'],
            'offsiteKeep' => ['required', 'integer', 'min:1', 'max:100'],
        ], [], [
            'frequency' => __('how often'),
            'keep' => __('backups to keep'),
            'offsiteKeep' => __('off-site copies to keep'),
        ]);

        $action->handle([
            'backups_enabled' => $validated['enabled'],
            'backup_frequency' => $validated['frequency'],
            'backups_to_keep' => $validated['keep'],
            'backup_include_files' => $validated['includeFiles'],
            'offsite_backups_to_keep' => $validated['offsiteKeep'],
        ]);

        Flux::toast(__('Backup settings saved.'), variant: 'success');
    }

    public function backUpNow(): void
    {
        $this->authorize('settings.edit');

        $backups = resolve(BackupService::class);

        if ($backups->running()) {
            Flux::toast(__('A backup is already running.'), variant: 'warning');

            return;
        }

        $backups->markPending();

        dispatch(new RunBackup(auth()->user()?->id));
    }

    public function refreshState(): void
    {
        unset($this->backups, $this->offsiteFiles, $this->rows);
    }

    public function dismissState(): void
    {
        $this->authorize('settings.edit');

        resolve(BackupService::class)->clearState();
    }

    public function confirmDelete(string $file): void
    {
        $this->authorize('settings.edit');

        $this->deleteFile = $file;

        Flux::modal('delete-backup')->show();
    }

    public function delete(): void
    {
        $this->authorize('settings.edit');

        $deleted = $this->deleteFile !== null && resolve(BackupService::class)->delete($this->deleteFile);

        if ($deleted) {
            ActivityLog::record(ActivityEvent::BACKUP_DELETED, $this->deleteFile);
        }

        $this->deleteFile = null;
        unset($this->backups, $this->rows);

        Flux::modal('delete-backup')->close();

        $deleted
            ? Flux::toast(__('Backup deleted.'), variant: 'success')
            : Flux::toast(__('That backup could not be deleted.'), variant: 'danger');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Backups'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.settings-layout>
    <div class="grid items-start gap-10 md:grid-cols-5">
        <div class="space-y-10 md:col-span-3">
            <form
                wire:submit="update"
                wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
                class="space-y-6"
            >
                <flux:switch
                    wire:model.live="enabled"
                    align="left"
                    :label="__('Automatic backups')"
                    :description="__('Back up the database and uploaded files on a schedule. Backups take disk space.')"
                />

                @if ($enabled)
                    <div class="grid gap-6 sm:grid-cols-2">
                        <flux:select wire:model="frequency" :label="__('How often')">
                            <flux:select.option value="daily">{{ __('Daily') }}</flux:select.option>
                            <flux:select.option value="weekly">{{ __('Weekly') }}</flux:select.option>
                        </flux:select>

                        <flux:input type="number" min="1" max="30" wire:model="keep" :label="__('Backups to keep')" />
                    </div>

                    <flux:switch
                        wire:model="includeFiles"
                        align="left"
                        :label="__('Include uploaded files')"
                        :description="__('Images, videos and documents from the media library.')"
                    />
                @endif

                @if ($this->offsiteConnected)
                    <flux:input
                        type="number"
                        min="1"
                        max="100"
                        wire:model="offsiteKeep"
                        :label="__('Off-site copies to keep')"
                        :description="__('Every backup is also copied to :location.', ['location' => resolve(\App\Services\OffsiteBackupService::class)->location()])"
                        class="sm:max-w-xs"
                    />
                @else
                    <flux:text>
                        {{ __('Keep a copy off this server in case it is lost:') }}
                        <flux:link :href="route('admin.settings-integrations')" wire:navigate>
                            {{ __('connect off-site backups') }}</flux:link>
                    </flux:text>
                @endif

                <flux:text>{{ $this->diskSummary() }}</flux:text>

                @can('settings.edit')
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                @endcan
            </form>

            <flux:separator variant="subtle" />

            @php($state = $this->state())

            <div class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Backups') }}</flux:heading>

                    @can('settings.edit')
                        <flux:button
                            wire:click="backUpNow"
                            icon="arrow-path"
                            variant="filled"
                            :disabled="in_array($state['status'], ['pending', 'running'], true)"
                        >
                            {{ __('Back up now') }}</flux:button>
                    @endcan
                </div>

                @if (in_array($state['status'], ['pending', 'running'], true))
                    <div wire:poll.3s="refreshState">
                        <flux:callout icon="arrow-path" variant="secondary">
                            <flux:callout.heading>{{ __('Backing up…') }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ $state['status'] === 'pending' ? __('Waiting for the scheduler to start the backup. This takes up to a minute.') : __('This can take a few minutes on a large site.') }}</flux:callout.text>
                        </flux:callout>
                    </div>
                @elseif ($state['status'] === 'finished')
                    <flux:callout icon="check-circle" variant="success">
                        <flux:callout.heading>{{ __('Backup ready') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ $state['file'] }}
                            <div class="mt-3">
                                <flux:button size="sm" variant="ghost" wire:click="dismissState">
                                    {{ __('Dismiss') }}</flux:button>
                            </div>
                        </flux:callout.text>
                    </flux:callout>
                @elseif ($state['status'] === 'failed' || $state['status'] === 'stalled')
                    <flux:callout icon="exclamation-triangle" variant="danger">
                        <flux:callout.heading>
                            {{ $state['status'] === 'stalled' ? __('The backup stopped responding. Is the scheduler’s cron entry running?') : __('The backup failed') }}</flux:callout.heading>
                        <flux:callout.text>
                            @if ($state['output'] !== null && $state['output'] !== '')
                                <pre class="mt-3 max-h-64 overflow-auto rounded-lg bg-zinc-900 p-3 text-xs whitespace-pre-wrap text-zinc-100">{{ $state['output'] }}</pre>
                            @endif
                            <div class="mt-3">
                                <flux:button size="sm" variant="ghost" wire:click="dismissState">
                                    {{ __('Dismiss') }}</flux:button>
                            </div>
                        </flux:callout.text>
                    </flux:callout>
                @endif

                @php($offsiteState = $this->offsiteState())

                @if ($offsiteState !== null && $offsiteState['status'] === 'copying')
                    <div wire:poll.3s="refreshState">
                        <flux:callout icon="cloud-arrow-up" variant="secondary">
                            <flux:callout.heading>
                                {{ __('Copying :file off-site…', ['file' => $offsiteState['file']]) }}</flux:callout.heading>
                        </flux:callout>
                    </div>
                @elseif ($offsiteState !== null)
                    <flux:callout icon="exclamation-triangle" variant="danger">
                        <flux:callout.heading>
                            {{ __('Copying :file off-site failed', ['file' => $offsiteState['file']]) }}</flux:callout.heading>
                        <flux:callout.text>
                            @if ($offsiteState['output'] !== null && $offsiteState['output'] !== '')
                                <pre class="mt-3 max-h-64 overflow-auto rounded-lg bg-zinc-900 p-3 text-xs whitespace-pre-wrap text-zinc-100">{{ $offsiteState['output'] }}</pre>
                            @endif
                            @can('settings.edit')
                                <div class="mt-3 flex gap-2">
                                    <flux:button size="sm" variant="filled" wire:click="retryOffsite">
                                        {{ __('Try again') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="dismissOffsite">
                                        {{ __('Dismiss') }}</flux:button>
                                </div>
                            @endcan
                        </flux:callout.text>
                    </flux:callout>
                @endif

                @if ($this->offsiteConnected && $this->offsiteFiles === null)
                    <flux:text variant="subtle">
                        {{ __('Off-site storage could not be reached, so off-site copies are not shown.') }}</flux:text>
                @endif

                @if ($this->rows === [])
                    <flux:text variant="subtle">{{ __('No backups yet.') }}</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Date') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Size') }}</flux:table.column>
                            <flux:table.column class="w-10"></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->rows as $backup)
                                <flux:table.row wire:key="backup-{{ $backup['file'] }}">
                                    <flux:table.cell class="whitespace-nowrap">
                                        {{ $backup['created_at']->translatedFormat('M j, Y H:i') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge
                                            size="sm"
                                            :color="$backup['kind'] === 'update' ? 'zinc' : ($backup['kind'] === 'automatic' ? 'sky' : 'green')"
                                        >
                                            {{
                                                match ($backup['kind']) {
                                                    'automatic' => __('Automatic'),
                                                    'manual' => __('Manual'),
                                                    default => __('Before update'),
                                                }
                                            }}</flux:badge>
                                        @if (! $backup['local'])
                                            <flux:text
                                                size="sm"
                                                class="ms-2 inline"
                                            >{{ __('Off-site only') }}</flux:text>
                                        @elseif ($backup['offsite'])
                                            <flux:tooltip :content="__('Also copied off-site')">
                                                <flux:icon
                                                    name="cloud"
                                                    variant="micro"
                                                    class="ms-2 inline text-zinc-400"
                                                    aria-label="{{ __('Also copied off-site') }}"
                                                />
                                            </flux:tooltip>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">
                                        {{ \Illuminate\Support\Number::fileSize($backup['bytes'], precision: 1) }}</flux:table.cell>
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
                                                        icon="arrow-down-tray"
                                                        href="{{ $backup['local'] ? route('admin.backups-download', ['file' => $backup['file']]) : route('admin.backups-offsite-download', ['file' => $backup['file']]) }}"
                                                    >
                                                        {{ __('Download') }}</flux:menu.item>
                                                    @if ($backup['local'] && $backup['kind'] !== 'update')
                                                        <flux:menu.separator />
                                                        <flux:menu.item
                                                            icon="trash"
                                                            variant="danger"
                                                            wire:click="confirmDelete('{{ $backup['file'] }}')"
                                                        >
                                                            {{ __('Delete') }}</flux:menu.item>
                                                    @endif
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endcan
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif

                <flux:text variant="subtle">
                    {{ __('To restore a backup, run on the server:') }}
                    <code class="text-xs">{{ 'php artisan wireup:backup:restore <file>' }}</code>
                    @if ($this->offsiteConnected)
                        {{ __('For a copy that is only off-site, run this first:') }}
                        <code class="text-xs">{{ 'php artisan wireup:backup:fetch <file>' }}</code>
                    @endif
                </flux:text>
            </div>
        </div>
    </div>

    <flux:modal name="delete-backup" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete backup') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Are you sure you want to delete this backup? This cannot be undone.') }}</flux:text>
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
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('Backups') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Backups') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
