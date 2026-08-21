<?php

declare(strict_types=1);

use App\Jobs\RunSiteImport;
use App\Services\SiteExporter;
use App\Services\SiteImporter;
use App\Services\TransferService;
use App\Services\UploadLimit;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

return new class extends Component
{
    use WithFileUploads;

    public bool $withSecrets = false;

    public ?TemporaryUploadedFile $bundle = null;

    #[Validate(['nullable', 'string', 'max:1024'])]
    public string $serverPath = '';

    /**
     * @var array{manifest: array<string, mixed>, problems: array<int, string>, tables: array<string, int>, media: array{expected: int, present: int, missing: array<int, string>}, imported: bool}|null
     */
    public ?array $inspection = null;

    public function maxUploadKilobytes(): int
    {
        return UploadLimit::cappedKilobytes(UploadLimit::VIDEO_MAX_KILOBYTES);
    }

    /**
     * @return array{status: string, bundle: ?string, step: ?string, output: ?string, at: ?CarbonImmutable}
     */
    public function state(): array
    {
        return resolve(TransferService::class)->state();
    }

    public function refreshState(): void
    {
        $this->inspection = null;
    }

    public function export(SiteExporter $exporter): StreamedResponse
    {
        $this->authorize('settings.edit');

        $directory = config()->string('wireup.transfer_path');
        $name = 'site-'.now()->format('Y-m-d-His').'.zip';

        $exporter->export($directory.'/'.$name, $this->withSecrets);

        return response()->streamDownload(
            function () use ($directory, $name): void {
                echo (string) File::get($directory.'/'.$name);
            },
            $name,
            ['Content-Type' => 'application/zip'],
        );
    }

    public function inspect(SiteImporter $importer): void
    {
        $this->authorize('settings.edit');

        $path = $this->resolveBundlePath();

        if ($path === null) {
            return;
        }

        try {
            $this->inspection = $importer->inspect($path);
        } catch (Throwable $exception) {
            $this->inspection = null;

            Flux::toast($exception->getMessage(), variant: 'danger');
        }
    }

    public function startImport(): void
    {
        $this->authorize('settings.edit');

        $transfers = resolve(TransferService::class);

        if ($transfers->importing()) {
            Flux::toast(__('An import is already running.'), variant: 'warning');

            return;
        }

        $path = $this->resolveBundlePath();

        if ($path === null) {
            return;
        }

        $stored = $this->stageBundle($path);

        $transfers->markPending(basename($stored));

        dispatch(new RunSiteImport($stored, auth()->id(), deleteBundle: true));

        $this->inspection = null;
        $this->bundle = null;

        Flux::modal('confirm-import')->close();
    }

    public function dismissState(): void
    {
        $this->authorize('settings.edit');

        resolve(TransferService::class)->clearState();
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Export / Import'))
            ->layout('layouts::admin');
    }

    private function resolveBundlePath(): ?string
    {
        if ($this->bundle instanceof TemporaryUploadedFile) {
            $this->validate(['bundle' => ['file', 'mimes:zip', 'max:'.$this->maxUploadKilobytes()]]);

            return $this->bundle->getRealPath();
        }

        $given = mb_trim($this->serverPath);

        if ($given === '') {
            Flux::toast(__('Upload a bundle or give a path to one on the server.'), variant: 'warning');

            return null;
        }

        $path = str_starts_with($given, '/') ? $given : base_path($given);

        if (! File::isFile($path)) {
            Flux::toast(__('No bundle found at that path.'), variant: 'danger');

            return null;
        }

        return $path;
    }

    private function stageBundle(string $path): string
    {
        $directory = config()->string('wireup.transfer_path');
        File::ensureDirectoryExists($directory);

        $staged = $directory.'/import-'.now()->format('Y-m-d-His').'.zip';

        File::copy($path, $staged);

        return $staged;
    }
};
?>

<x-admin.settings-layout>
    <div class="grid items-start gap-10 md:grid-cols-5">
        <div class="space-y-10 md:col-span-3">
            @php($state = $this->state())

            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Export this site') }}</flux:heading>
                    <flux:subheading>{{ __('Pages, records, media, menus and design in one file you can move to another install.') }}</flux:subheading>
                </div>

                <flux:switch
                    wire:model.live="withSecrets"
                    :label="__('Include API keys and mail credentials')"
                    :description="__('Off by default — these usually differ per environment.')"
                    align="left"
                />

                <flux:button wire:click="export" variant="primary" icon="arrow-down-tray">
                    {{ __('Download bundle') }}</flux:button>
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Import a site') }}</flux:heading>
                    <flux:subheading>{{ __('Replaces everything on this site. Users and submissions are left alone.') }}</flux:subheading>
                </div>

                @if (in_array($state['status'], ['pending', 'running'], true))
                    <div wire:poll.3s="refreshState">
                        <flux:callout icon="arrow-path" variant="secondary">
                            <flux:callout.heading>
                                {{ __('Importing :bundle…', ['bundle' => (string) $state['bundle']]) }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ $state['step'] ?? __('Waiting for the queue worker to start the import…') }}</flux:callout.text>
                        </flux:callout>
                    </div>
                @elseif ($state['status'] === 'finished')
                    <flux:callout icon="check-circle" variant="success">
                        <flux:callout.heading>
                            {{ __('Imported :bundle', ['bundle' => (string) $state['bundle']]) }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ $state['output'] }}
                            <div class="mt-2">
                                {{ __('If images do not load, run "php artisan storage:link" on this server.') }}
                            </div>
                            <div class="mt-3">
                                <flux:button size="sm" variant="ghost" wire:click="dismissState">
                                    {{ __('Dismiss') }}</flux:button>
                            </div>
                        </flux:callout.text>
                    </flux:callout>
                @elseif ($state['status'] === 'failed' || $state['status'] === 'stalled')
                    <flux:callout icon="exclamation-triangle" variant="danger">
                        <flux:callout.heading>
                            {{
                                $state['status'] === 'stalled'
                                ? __('The import stopped responding. Is the queue worker running?')
                                : __('Importing :bundle failed', ['bundle' => (string) $state['bundle']])
                            }}</flux:callout.heading>
                        <flux:callout.text>
                            @if ($state['output'] !== null && $state['output'] !== '')
                                <pre class="mt-3 max-h-64 overflow-auto rounded-lg bg-zinc-900 p-3 text-xs text-zinc-100">{{ $state['output'] }}</pre>
                            @endif
                            <div class="mt-3">
                                <flux:button size="sm" variant="ghost" wire:click="dismissState">
                                    {{ __('Dismiss') }}</flux:button>
                            </div>
                        </flux:callout.text>
                    </flux:callout>
                @endif

                @unless (in_array($state['status'], ['pending', 'running'], true))
                    <flux:field>
                        <flux:label>{{ __('Bundle file') }}</flux:label>
                        <flux:input type="file" wire:model="bundle" accept=".zip" />
                        <flux:description>
                            {{ __('Up to :size MB. For anything larger, copy the file onto the server and use the path below.', ['size' => number_format($this->maxUploadKilobytes() / 1024, 0)]) }}</flux:description>
                        <flux:error name="bundle" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Or a path on the server') }}</flux:label>
                        <flux:input wire:model.lazy="serverPath" placeholder="storage/app/transfers/site.zip" />
                        <flux:error name="serverPath" />
                    </flux:field>

                    <div class="flex flex-wrap gap-3">
                        <flux:button wire:click="inspect" icon="magnifying-glass" variant="filled">
                            {{ __('Check bundle') }}</flux:button>

                        @if ($inspection !== null && $inspection['problems'] === [])
                            <flux:modal.trigger name="confirm-import">
                                <flux:button variant="danger" icon="arrow-up-tray">
                                    {{ __('Replace this site') }}</flux:button>
                            </flux:modal.trigger>
                        @endif
                    </div>

                    @if ($inspection !== null)
                        <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                            <flux:heading size="sm">{{ __('Bundle contents') }}</flux:heading>

                            @if ($inspection['problems'] !== [])
                                @foreach ($inspection['problems'] as $problem)
                                    <flux:text class="text-red-600 dark:text-red-400">{{ $problem }}</flux:text>
                                @endforeach
                            @else
                                <div class="grid gap-1 sm:grid-cols-2">
                                    @foreach ($inspection['tables'] as $table => $count)
                                        @if ($count > 0)
                                            <flux:text class="tabular-nums">{{ $count }} {{ str($table)->replace('_', ' ') }}</flux:text>
                                        @endif
                                    @endforeach
                                    <flux:text class="tabular-nums">
                                        {{ $inspection['media']['present'] }} {{ __('media files') }}</flux:text>
                                </div>
                            @endif
                        </div>
                    @endif
                @endunless
            </div>
        </div>
    </div>

    <flux:modal name="confirm-import" class="md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Replace this site?') }}</flux:heading>
            <flux:text>{{ __('Every page, record, media item and setting is deleted and rebuilt from the bundle. This cannot be undone.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="startImport" variant="danger">{{ __('Replace site') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</x-admin.settings-layout>
