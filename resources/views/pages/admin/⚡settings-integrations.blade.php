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

    public string $head_scripts = '';

    public string $body_scripts = '';

    public function mount(): void
    {
        $this->pexels_api_key = is_string(config('site.pexels_api_key')) ? config()->string('site.pexels_api_key') : '';
        $this->google_analytics_id = is_string(config('site.google_analytics_id')) ? config()->string('site.google_analytics_id') : '';
        $this->head_scripts = is_string(config('site.head_scripts')) ? config()->string('site.head_scripts') : '';
        $this->body_scripts = is_string(config('site.body_scripts')) ? config()->string('site.body_scripts') : '';
    }

    public function update(UpdateSettingsAction $action): void
    {
        $validated = $this->validate([
            'pexels_api_key' => ['nullable', 'string', 'max:255'],
            'google_analytics_id' => ['nullable', 'string', 'max:40', 'regex:/^G-[A-Z0-9]+$/'],
            'head_scripts' => ['nullable', 'string', 'max:50000'],
            'body_scripts' => ['nullable', 'string', 'max:50000'],
        ], [
            'google_analytics_id.regex' => __('Enter a valid Google Analytics measurement ID, like G-XXXXXXXXXX.'),
        ], [
            'pexels_api_key' => __('Pexels API key'),
            'google_analytics_id' => __('Google Analytics measurement ID'),
            'head_scripts' => __('custom head code'),
            'body_scripts' => __('custom body code'),
        ]);

        $action->handle([
            'pexels_api_key' => $validated['pexels_api_key'] ?? '',
            'google_analytics_id' => $validated['google_analytics_id'] ?? '',
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
    <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="grid md:grid-cols-5 gap-10 items-start">
        <div class="space-y-10 md:col-span-3">
            <flux:input
                wire:model="pexels_api_key"
                type="password"
                viewable
                :label="__('Pexels API key')"
                :placeholder="__('Paste your Pexels API key…')"
                :description="__('Enables searching and importing photos and videos from Pexels in the media library. Create a free key at pexels.com/api.')"
            />

            <flux:input
                wire:model="google_analytics_id"
                :label="__('Google Analytics measurement ID')"
                placeholder="G-XXXXXXXXXX"
                :description="__('Adds the Google Analytics tracking snippet to your public site. Find this ID in your GA4 property’s data stream settings.')"
            />

            <flux:separator variant="subtle" />

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
        </div>
    </form>
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
