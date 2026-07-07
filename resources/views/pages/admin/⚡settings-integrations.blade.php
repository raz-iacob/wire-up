<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

return new class extends Component
{
    public string $pexels_api_key = '';

    public string $google_analytics_id = '';

    public string $google_maps_api_key = '';

    public string $head_scripts = '';

    public string $body_scripts = '';

    public function mount(): void
    {
        $this->pexels_api_key = is_string(config('site.pexels_api_key')) ? config()->string('site.pexels_api_key') : '';
        $this->google_analytics_id = is_string(config('site.google_analytics_id')) ? config()->string('site.google_analytics_id') : '';
        $this->google_maps_api_key = is_string(config('site.google_maps_api_key')) ? config()->string('site.google_maps_api_key') : '';
        $this->head_scripts = is_string(config('site.head_scripts')) ? config()->string('site.head_scripts') : '';
        $this->body_scripts = is_string(config('site.body_scripts')) ? config()->string('site.body_scripts') : '';
    }

    public function connectPexels(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validate([
            'pexels_api_key' => ['required', 'string', 'max:255'],
        ], [], [
            'pexels_api_key' => __('Pexels API key'),
        ]);

        $action->handle(['pexels_api_key' => $validated['pexels_api_key']]);

        Flux::modal('integration-pexels')->close();
        Flux::toast(__('Pexels connected.'), variant: 'success');
    }

    public function connectGoogleAnalytics(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validate([
            'google_analytics_id' => ['required', 'string', 'max:40', 'regex:/^G-[A-Z0-9]+$/'],
        ], [
            'google_analytics_id.regex' => __('Enter a valid Google Analytics measurement ID, like G-XXXXXXXXXX.'),
        ], [
            'google_analytics_id' => __('Google Analytics measurement ID'),
        ]);

        $action->handle(['google_analytics_id' => $validated['google_analytics_id']]);

        Flux::modal('integration-google-analytics')->close();
        Flux::toast(__('Google Analytics connected.'), variant: 'success');
    }

    public function connectGoogleMaps(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validate([
            'google_maps_api_key' => ['required', 'string', 'max:255'],
        ], [], [
            'google_maps_api_key' => __('Google Maps API key'),
        ]);

        $action->handle(['google_maps_api_key' => $validated['google_maps_api_key']]);

        Flux::modal('integration-google-maps')->close();
        Flux::toast(__('Google Maps connected.'), variant: 'success');
    }

    public function disconnect(string $integration, UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $field = match ($integration) {
            'pexels' => 'pexels_api_key',
            'google-analytics' => 'google_analytics_id',
            'google-maps' => 'google_maps_api_key',
            default => null,
        };

        if ($field === null) {
            return;
        }

        $this->{$field} = '';
        $action->handle([$field => '']);

        Flux::modal('integration-'.$integration)->close();
        Flux::toast(__('Disconnected.'), variant: 'success');
    }

    public function updateCustomCode(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validate([
            'head_scripts' => ['nullable', 'string', 'max:50000'],
            'body_scripts' => ['nullable', 'string', 'max:50000'],
        ], [], [
            'head_scripts' => __('custom head code'),
            'body_scripts' => __('custom body code'),
        ]);

        $action->handle([
            'head_scripts' => mb_trim((string) ($validated['head_scripts'] ?? '')),
            'body_scripts' => mb_trim((string) ($validated['body_scripts'] ?? '')),
        ]);

        Flux::toast(__('Settings have been updated.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Integrations'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.settings-layout>
    <div class="space-y-10">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @php($pexelsConnected = $pexels_api_key !== '')
            <flux:card class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-zinc-950/5 dark:bg-white/5 dark:ring-white/10">
                        <svg viewBox="0 0 32 32" class="size-7" xmlns="http://www.w3.org/2000/svg">
                            <rect width="32" height="32" rx="6" fill="#05A081" />
                            <path fill="#fff" d="M12.6 8.8h4.6c3 0 5.1 2 5.1 4.9s-2.1 4.9-5.1 4.9h-2.1v4.6h-2.5V8.8Zm2.5 2.3v5.2h1.9c1.6 0 2.7-1 2.7-2.6s-1.1-2.6-2.7-2.6h-1.9Z" />
                        </svg>
                    </div>
                    <flux:modal.trigger name="integration-pexels">
                        <flux:button size="sm" :variant="$pexelsConnected ? 'primary' : 'outline'" :icon="$pexelsConnected ? 'check' : null">
                            {{ $pexelsConnected ? __('Connected') : __('Connect') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>

                <div class="space-y-1">
                    <flux:heading size="lg">Pexels</flux:heading>
                    <flux:text>{{ __('Search and import free photos and videos in the media library.') }}</flux:text>
                </div>
            </flux:card>

            @php($gaConnected = $google_analytics_id !== '')
            <flux:card class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-zinc-950/5 dark:bg-white/5 dark:ring-white/10">
                        <svg viewBox="0 0 32 32" class="size-7" xmlns="http://www.w3.org/2000/svg">
                            <rect x="20" y="4" width="8" height="24" rx="4" fill="#F9AB00" />
                            <rect x="11" y="12" width="8" height="16" rx="4" fill="#E37400" />
                            <circle cx="7" cy="24.5" r="3.5" fill="#E37400" />
                        </svg>
                    </div>
                    <flux:modal.trigger name="integration-google-analytics">
                        <flux:button size="sm" :variant="$gaConnected ? 'primary' : 'outline'" :icon="$gaConnected ? 'check' : null">
                            {{ $gaConnected ? __('Connected') : __('Connect') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>

                <div class="space-y-1">
                    <flux:heading size="lg">{{ __('Google Analytics') }}</flux:heading>
                    <flux:text>{{ __('Add Google Analytics tracking to your public site.') }}</flux:text>
                </div>
            </flux:card>

            @php($mapsConnected = $google_maps_api_key !== '')
            <flux:card class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-zinc-950/5 dark:bg-white/5 dark:ring-white/10">
                        <svg viewBox="0 0 24 24" class="size-7" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#EA4335" d="M12 2c-3.9 0-7 3.1-7 7 0 4.9 7 13 7 13s7-8.1 7-13c0-3.9-3.1-7-7-7z" />
                            <circle cx="12" cy="9" r="2.6" fill="#fff" />
                        </svg>
                    </div>
                    <flux:modal.trigger name="integration-google-maps">
                        <flux:button size="sm" :variant="$mapsConnected ? 'primary' : 'outline'" :icon="$mapsConnected ? 'check' : null">
                            {{ $mapsConnected ? __('Connected') : __('Connect') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>

                <div class="space-y-1">
                    <flux:heading size="lg">{{ __('Google Maps') }}</flux:heading>
                    <flux:text>{{ __('Use the official Maps Embed API for location blocks.') }}</flux:text>
                </div>
            </flux:card>
        </div>

        <flux:modal name="integration-pexels" class="w-full md:max-w-lg">
            <form wire:submit="connectPexels" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Connect Pexels') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Enables searching and importing photos and videos from Pexels in the media library. Create a free key at pexels.com/api.') }}</flux:text>
                </div>

                <flux:input
                    wire:model="pexels_api_key"
                    type="password"
                    viewable
                    :label="__('Pexels API key')"
                    :placeholder="__('Paste your Pexels API key…')"
                />

                <div class="flex items-center justify-between gap-4">
                    @if ($pexels_api_key !== '')
                        <flux:button variant="subtle" wire:click="disconnect('pexels')">{{ __('Disconnect') }}</flux:button>
                    @else
                        <span></span>
                    @endif
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="integration-google-analytics" class="w-full md:max-w-lg">
            <form wire:submit="connectGoogleAnalytics" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Connect Google Analytics') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Adds the Google Analytics tracking snippet to your public site. Find this ID in your GA4 property’s data stream settings.') }}</flux:text>
                </div>

                <flux:input
                    wire:model="google_analytics_id"
                    :label="__('Google Analytics measurement ID')"
                    placeholder="G-XXXXXXXXXX"
                />

                <div class="flex items-center justify-between gap-4">
                    @if ($google_analytics_id !== '')
                        <flux:button variant="subtle" wire:click="disconnect('google-analytics')">{{ __('Disconnect') }}</flux:button>
                    @else
                        <span></span>
                    @endif
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="integration-google-maps" class="w-full md:max-w-lg">
            <form wire:submit="connectGoogleMaps" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Connect Google Maps') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Uses the Google Maps Embed API for location blocks. Enable the Maps Embed API and create a key in the Google Cloud console.') }}</flux:text>
                </div>

                <flux:input
                    wire:model="google_maps_api_key"
                    type="password"
                    viewable
                    :label="__('Google Maps API key')"
                    :placeholder="__('Paste your Google Maps API key…')"
                />

                <div class="flex items-center justify-between gap-4">
                    @if ($google_maps_api_key !== '')
                        <flux:button variant="subtle" wire:click="disconnect('google-maps')">{{ __('Disconnect') }}</flux:button>
                    @else
                        <span></span>
                    @endif
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:separator variant="subtle" />

        <form wire:submit="updateCustomCode" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="max-w-3xl space-y-6">
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Custom code') }}</flux:heading>
                <flux:text>{{ __('Paste tracking pixels, analytics or other third-party snippets. Code is added as-is to every page on your public site, so only add code from sources you trust.') }}</flux:text>
                <div class="flex flex-wrap items-center gap-3">
                    <flux:modal.trigger name="site-head-scripts">
                        <flux:button icon="code-bracket" variant="filled">{{ $head_scripts !== '' ? __('Edit head code') : __('Add head code') }}</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger name="site-body-scripts">
                        <flux:button icon="code-bracket" variant="filled">{{ $body_scripts !== '' ? __('Edit body code') : __('Add body code') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <flux:modal name="site-head-scripts" class="w-full md:max-w-2xl">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Custom head code') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Added inside the <head> tag on every page. Use for Facebook Pixel, BugHerd, verification tags and similar snippets.') }}</flux:text>
                    </div>
                    <flux:textarea wire:model="head_scripts" rows="12" class="font-mono text-sm" placeholder="&lt;script&gt;…&lt;/script&gt;" />
                    <div class="flex justify-end">
                        <flux:modal.close>
                            <flux:button variant="primary">{{ __('Done') }}</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>

            <flux:modal name="site-body-scripts" class="w-full md:max-w-2xl">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Custom body code') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Added just before the closing </body> tag on every page. Use for chat widgets and scripts that should load last.') }}</flux:text>
                    </div>
                    <flux:textarea wire:model="body_scripts" rows="12" class="font-mono text-sm" placeholder="&lt;script&gt;…&lt;/script&gt;" />
                    <div class="flex justify-end">
                        <flux:modal.close>
                            <flux:button variant="primary">{{ __('Done') }}</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Update') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ __('Integrations') }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Integrations') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
