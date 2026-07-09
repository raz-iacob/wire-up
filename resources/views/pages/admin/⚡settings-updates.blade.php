<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Services\UpdateService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public bool $auto_update = false;

    public function mount(): void
    {
        $this->auto_update = (bool) config('site.auto_update');
    }

    public function currentVersion(): ?string
    {
        return resolve(UpdateService::class)->currentVersion();
    }

    public function latestVersion(): ?string
    {
        return resolve(UpdateService::class)->latestVersion();
    }

    public function lastCheckedAt(): ?CarbonImmutable
    {
        return resolve(UpdateService::class)->lastCheckedAt();
    }

    public function updateAvailable(): bool
    {
        return resolve(UpdateService::class)->updateAvailable();
    }

    /**
     * @return array<int, array{version: string, notes: list<string>}>
     */
    public function changelog(): array
    {
        return resolve(UpdateService::class)->changelog();
    }

    /**
     * @return array{status: string, tag: ?string, step: ?string, output: ?string, at: ?CarbonImmutable}
     */
    public function state(): array
    {
        return resolve(UpdateService::class)->state();
    }

    public function checkNow(): void
    {
        $this->authorize('settings.edit');

        resolve(UpdateService::class)->check();

        $this->dispatch('updates-checked');

        Flux::toast(__('Checked for updates.'), variant: 'success');
    }

    public function startUpdate(): void
    {
        $this->authorize('settings.edit');

        $updates = resolve(UpdateService::class);
        $tag = $updates->latestVersion();

        if ($tag === null || ! $updates->updateAvailable() || $updates->updating()) {
            return;
        }

        $updates->markPending($tag);
        dispatch(new \App\Jobs\RunSystemUpdate($tag));

        Flux::modal('confirm-update')->close();
    }

    public function dismissState(): void
    {
        $this->authorize('settings.edit');

        resolve(UpdateService::class)->clearState();

        $this->dispatch('updates-checked');
    }

    public function refreshState(): void
    {
        //
    }

    public function updatedAutoUpdate(): void
    {
        $this->authorize('settings.edit');

        Flux::modal('confirm-auto-update')->show();
    }

    public function confirmAutoUpdate(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validate([
            'auto_update' => ['boolean'],
        ]);

        $action->handle([
            'auto_update' => $validated['auto_update'] ?? false,
        ]);

        Flux::modal('confirm-auto-update')->close();

        Flux::toast(__('Settings have been updated.'), variant: 'success');
    }

    public function cancelAutoUpdate(): void
    {
        $this->auto_update = ! $this->auto_update;

        Flux::modal('confirm-auto-update')->close();
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Updates'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.settings-layout>
    <div class="grid md:grid-cols-5 gap-10 items-start">
        <div class="space-y-10 md:col-span-3">
            @php($state = $this->state())

            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Current version') }} {{ $this->currentVersion() ?? __('Unknown') }}</flux:heading>
                    <flux:subheading>{{ __('Releases are checked once a day at midnight.') }}</flux:subheading>
                </div>

                <div class="space-y-3">
                    @can('settings.edit')
                        <flux:button wire:click="checkNow" size="sm" icon="arrow-path">{{ __('Check now') }}</flux:button>
                    @endcan
                    <flux:text>{{ __('Last checked :time', ['time' => $this->lastCheckedAt()?->diffForHumans() ?? __('never')]) }}</flux:text>
                </div>
            </div>

            @if (in_array($state['status'], ['pending', 'running'], true))
                <div wire:poll.3s="refreshState">
                    <flux:callout icon="arrow-path" variant="secondary">
                        <flux:callout.heading>{{ __('Updating to :tag…', ['tag' => (string) $state['tag']]) }}</flux:callout.heading>
                        <flux:callout.text>{{ $state['step'] ?? __('Waiting for the queue worker to start the update…') }}</flux:callout.text>
                    </flux:callout>
                </div>
            @elseif ($state['status'] === 'finished')
                <flux:callout icon="check-circle" variant="success">
                    <flux:callout.heading>{{ __('Updated to :tag.', ['tag' => (string) $state['tag']]) }}</flux:callout.heading>
                    <flux:callout.text>
                        @can('settings.edit')
                            <flux:button wire:click="dismissState" size="sm" class="mt-1">{{ __('Dismiss') }}</flux:button>
                        @endcan
                    </flux:callout.text>
                </flux:callout>
            @elseif ($state['status'] === 'failed')
                <flux:callout icon="exclamation-triangle" variant="danger">
                    <flux:callout.heading>{{ __('Update to :tag failed at: :step', ['tag' => (string) $state['tag'], 'step' => (string) $state['step']]) }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('The public site is in maintenance mode. Fix the issue on the server, then run: php artisan up') }}
                        @if ($state['output'] !== null && $state['output'] !== '')
                            <pre class="mt-3 max-h-64 overflow-auto rounded-lg bg-zinc-900 p-3 text-xs text-zinc-100">{{ $state['output'] }}</pre>
                        @endif
                        @can('settings.edit')
                            <flux:button wire:click="dismissState" size="sm" class="mt-3">{{ __('Dismiss') }}</flux:button>
                        @endcan
                    </flux:callout.text>
                </flux:callout>
            @elseif ($state['status'] === 'stalled')
                <flux:callout icon="exclamation-triangle" variant="warning">
                    <flux:callout.heading>{{ __('The update has not reported progress.') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Check that a queue worker is running on the server.') }}
                        @can('settings.edit')
                            <flux:button wire:click="dismissState" size="sm" class="mt-3">{{ __('Dismiss') }}</flux:button>
                        @endcan
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if ($this->updateAvailable() && ! in_array($state['status'], ['pending', 'running'], true))
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Update available') }}</flux:heading>
                        <flux:subheading>{{ __(':tag is ready to install.', ['tag' => (string) $this->latestVersion()]) }}</flux:subheading>
                    </div>

                    @php($changelog = $this->changelog())
                    @if ($changelog !== [])
                        <div class="space-y-4">
                            @foreach ($changelog as $section)
                                <div wire:key="changelog-{{ $section['version'] }}">
                                    <flux:heading size="sm">{{ $section['version'] }}</flux:heading>
                                    <ul class="mt-1 list-disc space-y-1 ps-5 text-sm text-zinc-600 dark:text-zinc-300">
                                        @foreach ($section['notes'] as $note)
                                            <li>{{ $note }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <flux:text>{{ __('No release notes.') }}</flux:text>
                    @endif

                    @can('settings.edit')
                        <flux:modal.trigger name="confirm-update">
                            <flux:button variant="primary" icon="arrow-down-tray">{{ __('Update now') }}</flux:button>
                        </flux:modal.trigger>
                    @endcan
                </div>
            @endif

            @can('settings.edit')
                <flux:separator />

                <flux:switch
                    wire:model.live="auto_update"
                    align="left"
                    :label="__('Automatic updates')"
                    :description="__('Install new releases automatically after the daily check.')"
                />
            @endcan
        </div>
    </div>

    <flux:modal name="confirm-update" class="w-full md:max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Update to :tag?', ['tag' => (string) $this->latestVersion()]) }}</flux:heading>
                <flux:subheading>{{ __('The public site goes into maintenance mode until the update finishes.') }}</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="startUpdate" variant="primary">{{ __('Update') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-auto-update" class="w-full md:max-w-md" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $auto_update ? __('Enable automatic updates?') : __('Disable automatic updates?') }}</flux:heading>
                <flux:subheading>{{ $auto_update ? __('New releases are installed unattended after the daily check. The public site goes into maintenance mode during each update.') : __('New releases will wait for you to install them.') }}</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelAutoUpdate">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="confirmAutoUpdate">{{ $auto_update ? __('Enable') : __('Disable') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ __('Updates') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Updates') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
